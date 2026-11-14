#!/usr/bin/env python3
"""
MySQL-protocol server backed by SQLite — lets the real Woodex PHP app run
unmodified (it speaks mysqli) against the imported database.

    python3 tools/php-run/mysql_bridge.py --db .tmp/php-live.sqlite --port 3306

How it works:
  * mysql-mimic implements the MySQL wire protocol (handshake, COM_QUERY,
    prepared statements, COM_PING, ...).
  * Every query is parsed with sqlglot, translated MySQL -> SQLite, and run
    against the SQLite mirror built from the cpanel dump.
  * MySQL-only functions the app uses (NOW, DATE_FORMAT, IF, CONCAT, ...) are
    registered as SQLite user functions so translated SQL still resolves.
"""
from __future__ import annotations

import argparse
import asyncio
import logging
import pathlib
import re
import sqlite3
import threading
from datetime import datetime
from typing import Any, Dict, Optional, Sequence

import sqlglot
from mysql_mimic import MysqlServer, Session
from mysql_mimic.auth import IdentityProvider, NativePasswordAuthPlugin, User
from mysql_mimic.constants import Capabilities
from sqlglot import exp

LOG = logging.getLogger("bridge")

# ---------------------------------------------------------------------------
# mysql-mimic reports "0 columns" in its COM_STMT_PREPARE_OK packet and never
# sends column definitions at prepare time. Real MySQL sends both, and PHP's
# mysqlnd sizes its per-statement result memory pool from that number: with 0
# it under-allocates, the column metadata received at execute overflows the
# pool, and the process dies later inside mysqlnd_mempool_destroy() with
# "null function or function signature mismatch". So we describe the statement
# exactly like a real server would.
# ---------------------------------------------------------------------------
DB_PATH: str = ""


def _table_columns(table: str) -> list[str]:
    """Column names for a table, straight from the SQLite mirror."""
    if not table or not DB_PATH:
        return []
    try:
        conn = sqlite3.connect(f"file:{DB_PATH}?mode=ro", uri=True)
        try:
            return [row[1] for row in conn.execute(f"PRAGMA table_info({table!r})")]
        finally:
            conn.close()
    except sqlite3.Error:
        return []


def _select_column_names(sql: str) -> list[str]:
    """Best-effort list of the result columns a SELECT will produce."""
    try:
        tree = sqlglot.parse_one(sql, read="mysql")
    except Exception:  # noqa: BLE001 - not a SELECT, leave metadata empty
        return []
    select = tree if isinstance(tree, exp.Select) else tree.find(exp.Select)
    if select is None:
        return []

    names: list[str] = []
    for projection in select.expressions:
        if isinstance(projection, exp.Star):
            tables = [t.name for t in select.find_all(exp.Table)]
            for table in tables:
                names.extend(_table_columns(table))
        elif isinstance(projection, exp.Column) and isinstance(projection.this, exp.Star):
            names.extend(_table_columns(projection.table))
        else:
            names.append(projection.alias_or_name or f"col{len(names) + 1}")
    return names


def _install_mysqlnd_compat_patches() -> None:
    """Make prepared statements look like a real MySQL server's."""
    from mysql_mimic import packets as mm_packets
    from mysql_mimic.connection import Connection
    from mysql_mimic.prepared import REGEX_PARAM, PreparedStatement

    original_prepare_ok = mm_packets.make_com_stmt_prepare_ok

    def make_com_stmt_prepare_ok(statement):  # noqa: ANN001
        packet = bytearray(original_prepare_ok(statement))
        # [0]=OK  [1:5]=stmt id  [5:7]=column count  [7:9]=param count
        count = len(getattr(statement, "column_names", []) or [])
        packet[5:7] = count.to_bytes(2, "little")
        return bytes(packet)

    original_prepare_response = Connection.com_stmt_prepare_response

    def com_stmt_prepare_response(self, statement):  # noqa: ANN001
        yield from original_prepare_response(self, statement)
        names = getattr(statement, "column_names", []) or []
        if names:
            for name in names:
                yield mm_packets.make_column_definition_41(
                    server_charset=self.server_charset, name=name
                )
            yield self.eof()

    async def handle_stmt_prepare(self, data: bytes) -> None:
        sql = self.client_charset.decode(data)
        stmt_id = next(self.prepared_stmt_seq)
        stmt = PreparedStatement(
            stmt_id=stmt_id,
            sql=sql,
            num_params=len(REGEX_PARAM.findall(sql)),
        )
        stmt.column_names = _select_column_names(sql)
        self.prepared_stmts[stmt_id] = stmt
        for packet in self.com_stmt_prepare_response(stmt):
            await self.stream.write(packet, drain=False)
        await self.stream.drain()

    # mysqlnd tracks the connection's autocommit state from OK packets; real
    # MySQL always reports SERVER_STATUS_AUTOCOMMIT here.
    from mysql_mimic.types import ServerStatus

    original_connection_init = Connection.__init__

    def __init__(self, *args, **kwargs):  # noqa: ANN001
        original_connection_init(self, *args, **kwargs)
        self.status_flags = ServerStatus.SERVER_STATUS_AUTOCOMMIT

    Connection.__init__ = __init__

    # A bare 7-byte OK packet (header + affected_rows + insert_id + status +
    # warnings) leaves mysqlnd with zero bytes to parse the optional "info"
    # field: php_mysqlnd_rset_header_read() then reads one byte past the packet,
    # takes a garbage length from the reused command buffer and fails the whole
    # response, which surfaces as mysqli_query() === false and, from then on,
    # "Commands out of sync" (errno 2014) on that connection. Sending an empty
    # length-encoded info field makes the packet unambiguous (8 bytes).
    original_ok = Connection.ok

    def ok(self, **kwargs):  # noqa: ANN001
        return original_ok(self, **kwargs) + b"\x00"

    Connection.ok = ok

    mm_packets.make_com_stmt_prepare_ok = make_com_stmt_prepare_ok
    Connection.com_stmt_prepare_response = com_stmt_prepare_response
    Connection.handle_stmt_prepare = handle_stmt_prepare


_DDL_INDEX_START = re.compile(r",\s*(?:UNIQUE\s+)?(?:KEY|INDEX|FULLTEXT|SPATIAL)\s+`?\w+`?\s*\(", re.IGNORECASE)
_DDL_ON_UPDATE = re.compile(r"\s+ON\s+UPDATE\s+CURRENT_TIMESTAMP(?:\(\d*\))?", re.IGNORECASE)
_DDL_ENUM = re.compile(r"\bENUM\s*\([^)]*\)", re.IGNORECASE)
_DDL_NAMED_UNIQUE = re.compile(r"\b(UNIQUE)\s+`?\w+`?\s*\(", re.IGNORECASE)
_DDL_AFTER_CLAUSE = re.compile(r"\s+AFTER\s+`?\w+`?", re.IGNORECASE)
_DDL_TABLE_OPTIONS = re.compile(r"\)\s*(?:ENGINE|DEFAULT\s+CHARSET|CHARSET|COLLATE|AUTO_INCREMENT|ROW_FORMAT)\b[^;]*$", re.IGNORECASE)


def _strip_index_clauses(sql: str) -> str:
    """Remove KEY/INDEX clauses from a CREATE TABLE, honouring nested parens.

    MySQL writes `UNIQUE KEY name (col(191))`; SQLite rejects the whole clause,
    and the tables (plus their indexes) already exist in the import.
    """
    while True:
        match = _DDL_INDEX_START.search(sql)
        if not match:
            return sql
        depth = 1
        index = match.end()
        while index < len(sql) and depth:
            if sql[index] == "(":
                depth += 1
            elif sql[index] == ")":
                depth -= 1
            index += 1
        sql = sql[: match.start()] + sql[index:]


def _sanitise_sqlite_ddl(sql: str) -> str:
    """Drop MySQL-only CREATE TABLE syntax that SQLite rejects.

    The application's bootstrap creates its tables on every request with
    `CREATE TABLE IF NOT EXISTS ...`, including KEY/INDEX clauses, ENGINE and
    ON UPDATE CURRENT_TIMESTAMP. The tables already exist in the import, so the
    cleanest behaviour is to accept the statement as a no-op instead of failing
    the whole page.
    """
    sql = _strip_index_clauses(sql)
    sql = _DDL_AFTER_CLAUSE.sub("", sql)
    sql = _DDL_NAMED_UNIQUE.sub("UNIQUE (", sql)
    sql = _DDL_ENUM.sub("TEXT", sql)
    sql = _DDL_ON_UPDATE.sub("", sql)
    sql = _DDL_TABLE_OPTIONS.sub(")", sql)
    return sql


class _AnyCredentials(IdentityProvider):
    """Accept every username/password pair.

    The application's connection bootstrap picks credentials by host name, so on
    a preview domain it tries the live cPanel user instead of the local one.
    This is a local harness over a SQLite copy, so authentication carries no
    meaning — the important thing is that any of those combinations connects.
    """

    class AnyPassword(NativePasswordAuthPlugin):
        def empty_password_quickpath(self, user: User, scramble: bytes) -> bool:  # noqa: D102
            return True

        def password_matches(self, user: User, scramble: bytes, nonce: bytes) -> bool:  # noqa: D102
            return True

    async def get_user(self, username: str) -> User:
        return User(name=username, auth_plugin=self.AnyPassword.name)

    def get_plugins(self):  # noqa: ANN201, D102
        return [self.AnyPassword()]

    def get_default_plugin(self):  # noqa: ANN201, D102
        return self.AnyPassword()

    def get_plugin(self, name: str):  # noqa: ANN201, D102
        return self.AnyPassword() if name == self.AnyPassword.name else None


def _register_functions(conn: sqlite3.Connection) -> None:
    """MySQL functions the application relies on."""

    conn.create_function("now", 0, lambda: datetime.now().strftime("%Y-%m-%d %H:%M:%S"))
    conn.create_function("sysdate", 0, lambda: datetime.now().strftime("%Y-%m-%d %H:%M:%S"))
    conn.create_function("curdate", 0, lambda: datetime.now().strftime("%Y-%m-%d"))
    conn.create_function("curtime", 0, lambda: datetime.now().strftime("%H:%M:%S"))
    conn.create_function("unix_timestamp", 0, lambda: int(datetime.now().timestamp()))
    conn.create_function(
        "unix_timestamp",
        1,
        lambda value: int(datetime.fromisoformat(str(value)).timestamp()) if value else 0,
    )
    conn.create_function(
        "from_unixtime",
        1,
        lambda value: datetime.fromtimestamp(int(value or 0)).strftime("%Y-%m-%d %H:%M:%S"),
    )
    conn.create_function("concat", -1, lambda *args: "".join("" if a is None else str(a) for a in args))
    conn.create_function(
        "concat_ws",
        -1,
        lambda sep, *args: str(sep).join(str(a) for a in args if a is not None),
    )
    conn.create_function("if", 3, lambda cond, a, b: a if cond else b)
    conn.create_function("ifnull", 2, lambda a, b: b if a is None else a)
    conn.create_function("year", 1, lambda v: int(str(v)[:4]) if v else None)
    conn.create_function("month", 1, lambda v: int(str(v)[5:7]) if v else None)
    conn.create_function("day", 1, lambda v: int(str(v)[8:10]) if v else None)
    conn.create_function("rand", 0, lambda: 0.42)
    conn.create_function("last_insert_id", 0, lambda: 0)

    def _date_format(value: Any, fmt: str) -> str:
        if value is None:
            return ""
        text = str(value)
        try:
            moment = datetime.fromisoformat(text)
        except ValueError:
            return text
        replacements = [
            ("%Y", "%Y"), ("%y", "%y"), ("%m", "%m"), ("%d", "%d"),
            ("%H", "%H"), ("%i", "%M"), ("%s", "%S"), ("%M", "%B"), ("%b", "%b"),
            ("%e", "%-d"), ("%c", "%-m"), ("%T", "%H:%M:%S"), ("%r", "%I:%M:%S %p"),
        ]
        out = fmt
        for src, dst in replacements:
            out = out.replace(src, dst)
        try:
            return moment.strftime(out)
        except ValueError:
            return text

    conn.create_function("date_format", 2, _date_format)
    conn.create_function("date", 1, lambda v: str(v)[:10] if v else None)
    conn.create_function("datediff", 2, lambda a, b: (datetime.fromisoformat(str(a)) - datetime.fromisoformat(str(b))).days)
    conn.create_function("greatest", -1, lambda *a: max([x for x in a if x is not None], default=None))
    conn.create_function("least", -1, lambda *a: min([x for x in a if x is not None], default=None))
    conn.create_function("group_concat", -1, lambda *a: ",".join(str(x) for x in a if x is not None))


class SQLiteSession(Session):
    """One SQLite connection per MySQL connection."""

    def __init__(self, db_path: str, trace: Optional[str] = None):
        super().__init__()
        self.db_path = db_path
        self.trace_path = trace
        self._conn: Optional[sqlite3.Connection] = None
        self._lock = threading.Lock()

    @property
    def conn(self) -> sqlite3.Connection:
        if self._conn is None:
            self._conn = sqlite3.connect(self.db_path, check_same_thread=False, timeout=10)
            self._conn.row_factory = None
            self._conn.execute("PRAGMA foreign_keys = OFF")
            _register_functions(self._conn)
        return self._conn

    def _trace(self, sql: str, error: Optional[str] = None) -> None:
        if not self.trace_path:
            return
        with open(self.trace_path, "a", encoding="utf-8") as handle:
            handle.write(("ERR " if error else "OK  ") + sql.replace("\n", " ")[:600] + (f"  -- {error}" if error else "") + "\n")

    async def query(self, expression: exp.Expression, sql: str, attrs: Dict[str, str]):
        text = sql.strip().rstrip(";")
        lowered = text.lower()

        # The app's self-healing schema code issues SHOW COLUMNS FROM x LIKE 'y'.
        match = re.match(r"show\s+columns\s+from\s+`?(\w+)`?\s+like\s+'([^']+)'", lowered)
        if match:
            table, column = match.group(1), match.group(2)
            rows = [
                (row[1], row[2] or "", "YES" if not row[3] else "NO", row[4], row[5] or "", "")
                for row in self.conn.execute(f"PRAGMA table_info({table})")
                if row[1].lower() == column
            ]
            return rows, ["Field", "Type", "Null", "Key", "Default", "Extra"]

        # Every request re-runs the app's schema bootstrap. The imported dump
        # already contains all 41 tables, so a CREATE TABLE IF NOT EXISTS for an
        # existing table is a no-op — short-circuit it instead of arguing with
        # SQLite about AUTO_INCREMENT, ENUM, named UNIQUE constraints, ...
        create = re.match(r"\s*create\s+table\s+(?:if\s+not\s+exists\s+)?`?(\w+)`?", text, re.IGNORECASE)
        if create:
            row = self.conn.execute(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", (create.group(1),)
            ).fetchone()
            if row:
                self._trace(text, "skipped: table already imported")
                return [], []

        # Simple DDL/DML passthrough for statements sqlglot cannot translate.
        translated_statements = []
        try:
            for statement in sqlglot.parse(text, read="mysql"):
                if statement is None:
                    continue
                # The app's self-healing bootstrap creates missing tables. MySQL
                # allows KEY/INDEX clauses inside CREATE TABLE, SQLite does not —
                # sqlglot keeps them, so drop them from the translated DDL.
                if isinstance(statement, exp.Create) and statement.args.get("indexes"):
                    statement.set("indexes", None)
                # SQLite's GROUP_CONCAT() takes (value, separator) and rejects the
                # MySQL ORDER BY clause inside the aggregate. sqlglot keeps that
                # ordering wrapped around the value, so unwrap it.
                for group_concat in statement.find_all(exp.GroupConcat):
                    value = group_concat.this
                    if isinstance(value, exp.Order):
                        group_concat.set("this", value.this.copy())
                    group_concat.set("order", None)
                translated = statement.sql(dialect="sqlite")
                if re.match(r"\s*(?:create|alter)\b", translated, re.IGNORECASE):
                    translated = _sanitise_sqlite_ddl(translated)
                translated_statements.append(translated)
        except Exception as error:  # noqa: BLE001 - fall back to raw SQL
            LOG.warning("sqlglot failed (%s); passing SQL through: %s", error, text[:120])
            translated_statements = [text]

        rows: Sequence[Sequence[Any]] = []
        columns: list[str] = []
        for statement in translated_statements:
            try:
                cursor = self.conn.execute(statement)
                if cursor.description:
                    columns = [item[0] for item in cursor.description]
                    rows = cursor.fetchall()
                else:
                    self.conn.commit()
                    rows = []
                    columns = []
            except Exception as error:  # noqa: BLE001 - report upward as MySQL error
                message = str(error)
                # The app's bootstrap tries to add columns that the imported
                # schema already contains — accept it instead of failing a page.
                if re.match(r"\s*alter\s+table", text, re.IGNORECASE) and (
                    "duplicate column name" in message or "already exists" in message
                ):
                    self._trace(text, f"ignored: {message}")
                    continue
                self._trace(text, message)
                raise
        self._trace(text)
        return rows, columns

    async def close(self) -> None:
        if self._conn is not None:
            try:
                self._conn.commit()
                self._conn.close()
            finally:
                self._conn = None
        await super().close()


async def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--db", required=True)
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=3306)
    parser.add_argument("--trace", default=None)
    args = parser.parse_args()

    global DB_PATH
    DB_PATH = str(pathlib.Path(args.db).resolve())

    import os

    logging.basicConfig(
        level=logging.DEBUG if os.environ.get("BRIDGE_DEBUG") else logging.INFO,
        format="%(asctime)s %(levelname)s %(name)s %(message)s",
    )

    trace = str(pathlib.Path(args.trace).resolve()) if args.trace else None

    _install_mysqlnd_compat_patches()

    def session_factory() -> Session:
        return SQLiteSession(args.db, trace)

    # mysqlnd (PHP's driver) mishandles CLIENT_DEPRECATE_EOF in some builds, and
    # the app never uses query attributes — advertise only what we can honour.
    capabilities = (
        Capabilities.CLIENT_LONG_PASSWORD
        | Capabilities.CLIENT_CONNECT_WITH_DB
        | Capabilities.CLIENT_PROTOCOL_41
        | Capabilities.CLIENT_SECURE_CONNECTION
        | Capabilities.CLIENT_PLUGIN_AUTH
        | Capabilities.CLIENT_PLUGIN_AUTH_LENENC_CLIENT_DATA
        | Capabilities.CLIENT_TRANSACTIONS
        | Capabilities.CLIENT_MULTI_STATEMENTS
        | Capabilities.CLIENT_MULTI_RESULTS
    )
    server = MysqlServer(
        session_factory=session_factory,
        identity_provider=_AnyCredentials(),
        capabilities=capabilities,
        host=args.host,
        port=args.port,
    )
    LOG.info("MySQL bridge listening on %s:%s -> %s", args.host, args.port, args.db)
    await server.serve_forever()


if __name__ == "__main__":
    asyncio.run(main())
