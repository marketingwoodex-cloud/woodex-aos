<?php
/**
 * Local-run compatibility layer for the Woodex PHP application.
 *
 * Why this exists
 * ---------------
 * The app is served by PHP 8.2 compiled to WebAssembly (php-wasm). In that
 * build, PHP's native driver (mysqlnd) corrupts its own heap as soon as a
 * prepared statement returns a result set — after a handful of statements the
 * whole PHP process aborts with
 *
 *   RuntimeError: null function or function signature mismatch
 *     at php.wasm.mysqlnd_read_header / mysqlnd_mempool_destroy
 *
 * Reproduced on PHP 8.2.35, 8.3.36 and 8.4.27, with and without
 * CLIENT_DEPRECATE_EOF, while the plain-text protocol path stays rock solid
 * (hundreds of queries, no crash). Two independent reference clients
 * (mysql-connector-python, mysql-mimic's own tests) read the exact same
 * prepared-statement responses without complaint, so the fault is in the WASM
 * mysqlnd build, not in the MySQL bridge.
 *
 * What this layer does
 * --------------------
 * WdxMysqli extends mysqli and overrides prepare() so prepared statements are
 * executed as *escaped text queries* on the same connection. Values are bound
 * with the usual bind_param() type string and quoted server-side by
 * real_escape_string(), so the SQL that reaches the bridge is equivalent to
 * what mysqlnd would send — only the wire path (COM_QUERY instead of
 * COM_STMT_EXECUTE) differs.
 *
 * The application source is untouched: only the three connection.php files
 * instantiate WdxMysqli instead of mysqli (see tools/php-run/apply-shim.sh).
 * Everything else — 329 prepare() calls in 104 files — keeps working as-is.
 */

if (!class_exists('WdxResult', false)) {
    /**
     * Drop-in stand-in for mysqli_result on the prepared-statement path.
     */
    class WdxResult
    {
        /** @var array<int, array<string, mixed>> */
        private array $rows;
        private int $position = 0;
        public int $num_rows = 0;

        public function __construct(array $rows)
        {
            $this->rows = array_values($rows);
            $this->num_rows = count($this->rows);
        }

        public function fetch_assoc(): ?array
        {
            return $this->rows[$this->position++] ?? null;
        }

        public function fetch_array(int $mode = MYSQLI_BOTH)
        {
            $row = $this->rows[$this->position++] ?? null;
            if ($row === null) {
                return null;
            }
            if ($mode === MYSQLI_ASSOC) {
                return $row;
            }
            if ($mode === MYSQLI_NUM) {
                return array_values($row);
            }
            $both = array_values($row);
            foreach ($row as $key => $value) {
                $both[$key] = $value;
            }
            return $both;
        }

        public function fetch_row(): ?array
        {
            $row = $this->rows[$this->position++] ?? null;
            return $row === null ? null : array_values($row);
        }

        public function fetch_all(int $mode = MYSQLI_NUM): array
        {
            $remaining = array_slice($this->rows, $this->position);
            $this->position = count($this->rows);
            if ($mode === MYSQLI_ASSOC) {
                return $remaining;
            }
            if ($mode === MYSQLI_NUM) {
                return array_map('array_values', $remaining);
            }
            return array_map(static function (array $row): array {
                $both = array_values($row);
                foreach ($row as $key => $value) {
                    $both[$key] = $value;
                }
                return $both;
            }, $remaining);
        }

        public function fetch_object(string $class = 'stdClass', array $constructor_args = []): ?object
        {
            $row = $this->rows[$this->position++] ?? null;
            if ($row === null) {
                return null;
            }
            $object = $class === 'stdClass' ? new stdClass() : (new ReflectionClass($class))->newInstanceWithoutConstructor();
            foreach ($row as $key => $value) {
                $object->{$key} = $value;
            }
            return $object;
        }

        public function data_seek(int $offset): bool
        {
            if ($offset < 0 || $offset >= count($this->rows)) {
                return false;
            }
            $this->position = $offset;
            return true;
        }

        public function field_count(): int
        {
            return $this->rows === [] ? 0 : count($this->rows[0]);
        }

        public function free(): void
        {
            $this->position = count($this->rows);
        }

        public function close(): void
        {
            $this->free();
        }

        public function free_result(): void
        {
            $this->free();
        }
    }
}

if (!class_exists('WdxStmt', false)) {
    /**
     * Statement object backed by an escaped text query.
     */
    class WdxStmt
    {
        public int $affected_rows = -1;
        public int $insert_id = 0;
        public int $num_rows = 0;
        public int $field_count = 0;
        public int $param_count = 0;
        public string $error = '';
        public int $errno = 0;

        private mysqli $link;
        private string $sql;
        private string $types = '';
        /** @var array<int, mixed> references to the caller's variables */
        private array $vars = [];
        /** @var array<int, mixed> references for bind_result() */
        private array $bound = [];
        /** @var array<int, array<string, mixed>> */
        private array $rows = [];
        private int $position = 0;

        public function __construct(mysqli $link, string $sql)
        {
            $this->link = $link;
            $this->sql = $sql;
            $this->param_count = substr_count($sql, '?');
        }

        public function bind_param(string $types, mixed &...$vars): bool
        {
            $this->types = $types;
            $this->vars = [];
            foreach ($vars as $index => &$var) {
                $this->vars[$index] = &$var;
            }
            $this->param_count = count($this->vars);
            return true;
        }

        public function bind_result(mixed &...$vars): bool
        {
            $this->bound = [];
            foreach ($vars as $index => &$var) {
                $this->bound[$index] = &$var;
            }
            return true;
        }

        public function execute(?array $params = null): bool
        {
            $values = $params ?? $this->vars;
            $sql = $this->interpolate($values);

            $result = $this->link->query($sql);
            if ($result === false) {
                $this->error = (string) $this->link->error;
                $this->errno = (int) $this->link->errno;
                $this->rows = [];
                $this->position = 0;
                $this->num_rows = 0;
                return false;
            }

            $this->error = '';
            $this->errno = 0;

            if ($result instanceof mysqli_result) {
                $this->rows = $result->fetch_all(MYSQLI_ASSOC);
                $result->free();
                $this->position = 0;
                $this->num_rows = count($this->rows);
                $this->field_count = $this->rows === [] ? 0 : count($this->rows[0]);
                $this->affected_rows = 0;
            } else {
                $this->rows = [];
                $this->position = 0;
                $this->num_rows = 0;
                $this->field_count = 0;
                $this->affected_rows = (int) $this->link->affected_rows;
                $this->insert_id = (int) $this->link->insert_id;
            }

            return true;
        }

        public function get_result(): WdxResult|false
        {
            return new WdxResult($this->rows);
        }

        public function store_result(int $mode = 0): bool
        {
            return true; // rows are already buffered
        }

        public function fetch(): ?bool
        {
            $row = $this->rows[$this->position++] ?? null;
            if ($row === null) {
                return null;
            }
            $values = array_values($row);
            foreach ($this->bound as $index => &$var) {
                $var = $values[$index] ?? null;
            }
            return true;
        }

        public function data_seek(int $offset): bool
        {
            if ($offset < 0 || $offset >= count($this->rows)) {
                return false;
            }
            $this->position = $offset;
            return true;
        }

        public function free_result(): void
        {
            $this->rows = [];
        }

        public function reset(): bool
        {
            $this->rows = [];
            $this->position = 0;
            $this->num_rows = 0;
            $this->affected_rows = -1;
            return true;
        }

        public function close(): bool
        {
            $this->rows = [];
            $this->bound = [];
            return true;
        }

        public function get_warnings(): false
        {
            return false;
        }

        /**
         * Replace ? placeholders with quoted literals, skipping anything inside
         * string literals or backtick-quoted identifiers.
         *
         * @param array<int, mixed> $values
         */
        private function interpolate(array $values): string
        {
            $sql = $this->sql;
            $length = strlen($sql);
            $out = '';
            $index = 0;
            $param = 0;

            while ($index < $length) {
                $char = $sql[$index];

                if ($char === "'" || $char === '"' || $char === '`') {
                    $out .= $char;
                    $index++;
                    while ($index < $length) {
                        $inner = $sql[$index];
                        if ($inner === '\\' && $index + 1 < $length) {
                            $out .= $inner . $sql[$index + 1];
                            $index += 2;
                            continue;
                        }
                        $out .= $inner;
                        $index++;
                        if ($inner === $char) {
                            break;
                        }
                    }
                    continue;
                }

                if ($char === '?') {
                    $out .= $this->literal($values[$param] ?? null, $this->types[$param] ?? 's');
                    $param++;
                    $index++;
                    continue;
                }

                $out .= $char;
                $index++;
            }

            return $out;
        }

        private function literal(mixed $value, string $type): string
        {
            if ($value === null) {
                return 'NULL';
            }

            switch ($type) {
                case 'i':
                    return (string) (int) $value;
                case 'd':
                    return $this->number((float) $value);
                default:
                    return "'" . $this->link->real_escape_string((string) $value) . "'";
            }
        }

        private function number(float $value): string
        {
            if (is_nan($value) || is_infinite($value)) {
                return '0';
            }
            $text = rtrim(rtrim(sprintf('%.14F', $value), '0'), '.');
            return $text === '' || $text === '-' ? '0' : $text;
        }
    }
}

if (!class_exists('WdxMysqli', false)) {
    /**
     * mysqli with prepared statements routed through escaped text queries.
     */
    class WdxMysqli extends mysqli
    {
        #[ReturnTypeWillChange]
        public function prepare($query)
        {
            return new WdxStmt($this, (string) $query);
        }

        /**
         * mysqlnd in WASM answers "Commands out of sync" to the SET NAMES packet
         * the real set_charset() sends, which poisons the whole connection. The
         * bridge speaks UTF-8 (utf8mb4) for every column and literal anyway, so
         * there is nothing to negotiate.
         */
        #[ReturnTypeWillChange]
        public function set_charset($charset)
        {
            return true;
        }

        public function character_set_name(): string
        {
            return 'utf8mb4';
        }

        /**
         * Statements that would go through the SET/variable short-circuit are
         * acknowledged locally for the same reason as set_charset().
         */
        #[ReturnTypeWillChange]
        public function query($query, $result_mode = MYSQLI_STORE_RESULT)
        {
            if (preg_match('/^\s*SET\s+/i', (string) $query)) {
                return true;
            }
            return parent::query($query, $result_mode);
        }

        #[ReturnTypeWillChange]
        public function real_query($query)
        {
            if (preg_match('/^\s*SET\s+/i', (string) $query)) {
                return true;
            }
            return parent::real_query($query);
        }
    }
}
