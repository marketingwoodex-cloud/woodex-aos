#!/usr/bin/env python3
"""Transparent MySQL TCP proxy that logs the wire conversation.

    python3 tools/php-run/trace_proxy.py --listen 3306 --upstream 3307 --log .tmp/mysql-wire.log

Used to see exactly what PHP's mysqlnd sends and what the bridge answers.
"""
from __future__ import annotations

import argparse
import asyncio
import pathlib
import sys


def describe(direction: str, seq: int, payload: bytes) -> str:
    head = payload[:1]
    first = head[0] if head else -1
    tag = {0x00: "OK", 0xFE: "EOF", 0xFF: "ERR", 0x0A: "handshake", 0x03: "COM_QUERY",
           0x16: "COM_STMT_PREPARE", 0x17: "COM_STMT_EXECUTE", 0x19: "COM_STMT_CLOSE",
           0x01: "quit"}.get(first, f"0x{first:02x}")
    text = ""
    if first in (0x03, 0x16):
        text = payload[1:120].decode("utf-8", "replace").replace("\n", " ")
    elif len(payload) <= 40:
        text = payload.hex()
    return f"{direction} seq={seq} len={len(payload):4d} {tag:16} {text}"


async def pump(reader: asyncio.StreamReader, writer: asyncio.StreamWriter, direction: str, log) -> None:
    buffer = b""
    while True:
        try:
            chunk = await reader.read(65536)
        except (ConnectionResetError, asyncio.IncompleteReadError):
            break
        if not chunk:
            break
        writer.write(chunk)
        await writer.drain()
        buffer += chunk
        # split packets (3-byte length + 1-byte seq)
        while len(buffer) >= 4:
            length = buffer[0] | buffer[1] << 8 | buffer[2] << 16
            if len(buffer) < 4 + length:
                break
            payload = buffer[4:4 + length]
            log.write(describe(direction, buffer[3], payload) + "\n")
            log.flush()
            buffer = buffer[4 + length:]
    try:
        writer.close()
    except Exception:  # noqa: BLE001
        pass


async def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--listen", type=int, default=3306)
    parser.add_argument("--upstream", type=int, default=3307)
    parser.add_argument("--log", default=".tmp/mysql-wire.log")
    args = parser.parse_args()

    path = pathlib.Path(args.log)
    path.parent.mkdir(parents=True, exist_ok=True)
    log = path.open("a", buffering=1, encoding="utf-8")

    async def handle(client_reader, client_writer):
        try:
            server_reader, server_writer = await asyncio.open_connection("127.0.0.1", args.upstream)
        except OSError as error:
            print(f"upstream refused: {error}", file=sys.stderr)
            client_writer.close()
            return
        log.write("--- new connection ---\n")
        await asyncio.gather(
            pump(client_reader, server_writer, "C→S", log),
            pump(server_reader, client_writer, "S→C", log),
        )

    server = await asyncio.start_server(handle, "127.0.0.1", args.listen)
    print(f"tracing proxy {args.listen} -> {args.upstream}, log {path}")
    async with server:
        await server.serve_forever()


if __name__ == "__main__":
    asyncio.run(main())
