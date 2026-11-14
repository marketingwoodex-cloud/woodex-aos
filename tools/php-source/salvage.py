#!/usr/bin/env python3
"""
Salvage members from a truncated .zip by walking its local file headers.

A zip's central directory lives at the very end of the file, so a truncated or
incomplete archive cannot be opened normally — even though 97% of the member
data may be intact. This walks forward from the first local header
(PK\\x03\\x04), inflates each member with raw deflate, and writes everything it
can fully recover, reporting what was cut off.

    python3 tools/php-source/salvage.py assembled.zip -d php-source
"""
from __future__ import annotations

import argparse
import struct
import sys
import zlib
from pathlib import Path

LOCAL = b"PK\x03\x04"
DESCRIPTOR = b"PK\x07\x08"


def salvage(path: Path, dest: Path, verbose: bool = False) -> dict:
    blob = path.read_bytes()
    size = len(blob)
    dest.mkdir(parents=True, exist_ok=True)

    stats = {"members": 0, "written": 0, "truncated": 0, "skipped_dirs": 0, "bytes": 0}
    truncated: list[str] = []
    pos = 0

    while True:
        start = blob.find(LOCAL, pos)
        if start < 0:
            break
        pos = start + 4
        if start + 30 > size:
            break

        (_sig, _ver, flags, method, _mtime, _mdate, crc, csize, usize,
         name_len, extra_len) = struct.unpack("<4sHHHHHIIIHH", blob[start:start + 30])

        name_start = start + 30
        name_end = name_start + name_len
        if name_end > size:
            break
        raw_name = blob[name_start:name_end]
        try:
            name = raw_name.decode("utf-8")
        except UnicodeDecodeError:
            name = raw_name.decode("latin-1")

        # A local header is only believable if it looks like a path.
        if not name or ("/" not in name and "." not in name) or "\x00" in name:
            continue

        data_start = name_end + extra_len
        if data_start > size:
            break

        stats["members"] += 1

        if name.endswith("/"):
            stats["skipped_dirs"] += 1
            (dest / name).mkdir(parents=True, exist_ok=True)
            continue

        target = dest / name
        # Path traversal guard — never write outside the destination.
        try:
            target.resolve().relative_to(dest.resolve())
        except ValueError:
            print(f"  ! skipping unsafe path {name!r}", file=sys.stderr)
            continue

        descriptor_used = bool(flags & 0x08) or csize == 0
        payload: bytes | None = None
        consumed = 0
        complete = True

        if method == 0:  # stored
            if csize and data_start + csize <= size:
                payload = blob[data_start:data_start + csize]
                consumed = csize
            else:
                complete = False
        elif method == 8:  # deflate
            inflater = zlib.decompressobj(-15)
            chunk = blob[data_start:data_start + (csize or (size - data_start))]
            try:
                payload = inflater.decompress(chunk)
                payload += inflater.flush()
                consumed = len(chunk) - len(inflater.unused_data)
                if not inflater.eof:
                    # Data ended mid-stream: keep what inflated, flag it.
                    complete = bool(descriptor_used) is False and False
                    complete = False
            except zlib.error:
                payload = None
                complete = False
        else:
            # Unsupported method (bzip2/lzma/store with descriptors) — skip header.
            continue

        if payload is None:
            stats["truncated"] += 1
            truncated.append(name)
            pos = data_start
            continue

        if method == 8 and not complete:
            # Partial inflate still yields usable source text for text files.
            stats["truncated"] += 1
            truncated.append(name)
        else:
            stats["written"] += 1
            stats["bytes"] += len(payload)

        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(payload)
        if verbose:
            print(f"  {'~' if not complete else '+'} {name} ({len(payload)} B)")

        if descriptor_used and method == 8:
            # Streamed entry: resume scanning right after the inflated stream.
            pos = data_start + consumed
            marker = blob.find(DESCRIPTOR, pos)
            if marker != -1 and marker - pos < 32:
                pos = marker
        else:
            pos = data_start + consumed

        if pos >= size:
            break

    return {"stats": stats, "truncated": truncated}


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("archive", type=Path)
    ap.add_argument("-d", "--dest", type=Path, default=Path("php-source"))
    ap.add_argument("-v", "--verbose", action="store_true")
    args = ap.parse_args()

    if not args.archive.exists():
        print(f"No such archive: {args.archive}", file=sys.stderr)
        return 1

    result = salvage(args.archive, args.dest, args.verbose)
    stats = result["stats"]
    print(f"members found   {stats['members']}")
    print(f"files recovered {stats['written']} ({stats['bytes'] / 1048576:.1f} MiB)")
    print(f"incomplete      {stats['truncated']}")
    print(f"directories     {stats['skipped_dirs']}")
    if result["truncated"]:
        print("\nTail of the archive (cut off by the upload):")
        for name in result["truncated"][:25]:
            print(f"  ~ {name}")
        if len(result["truncated"]) > 25:
            print(f"  … and {len(result['truncated']) - 25} more")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
