"""Back up supplied local legacy artifacts and verify/extract the ZIP privately.

Never connects to a server, executes legacy PHP, or changes the supplied inputs.
Run from WSL with Python's standard library. The original ZIP remains canonical.
"""

import argparse
import datetime
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import shutil
import stat
import zipfile


def sha256(path):
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def checked_copy(source, destination):
    before = source.stat()
    digest = sha256(source)
    if destination.exists():
        if sha256(destination) != digest:
            raise ValueError(f"Existing backup differs: {destination.name}")
    else:
        shutil.copy2(source, destination)
    destination.chmod(0o600)
    after = source.stat()
    if (before.st_size, before.st_mtime_ns) != (after.st_size, after.st_mtime_ns):
        raise ValueError("Input changed during backup")
    if sha256(destination) != digest:
        raise ValueError("Backup SHA-256 differs")
    return {"name": source.name, "bytes": before.st_size, "sha256": digest,
            "source_modified_utc": datetime.datetime.fromtimestamp(
                before.st_mtime, datetime.timezone.utc).isoformat()}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source-dir", type=Path, required=True)
    parser.add_argument("--workspace", type=Path, default=Path.cwd())
    args = parser.parse_args()
    root = args.workspace.resolve()
    source = args.source_dir.resolve()
    now = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=7)))
    backup = root / "backups/phase-0" / now.date().isoformat()
    private = root / "legacy-private/phase0"
    for directory in (root / "backups", root / "legacy-private", backup, private):
        directory.mkdir(parents=True, exist_ok=True)
        directory.chmod(0o700)
    artifacts = [checked_copy(source / name, backup / name)
                 for name in ("bancay.zip", "caybonmua_db.sql")]
    extracted = private / "extracted"
    extracted.mkdir(mode=0o700, exist_ok=True)
    links, seen, ordinary = [], set(), []
    with zipfile.ZipFile(backup / "bancay.zip") as archive:
        for entry in archive.infolist():
            name = PurePosixPath(entry.filename)
            if (name.is_absolute() or ".." in name.parts or
                    "\\" in entry.filename or (name.parts and name.parts[0].endswith(":")) or
                    entry.filename in seen or entry.flag_bits & 1):
                raise ValueError("Unsafe, duplicate or encrypted archive entry")
            seen.add(entry.filename)
            if stat.S_ISLNK(entry.external_attr >> 16):
                target = archive.read(entry).decode("utf-8")
                link_path = extracted / entry.filename
                if not (link_path.parent / target).resolve().is_relative_to(extracted):
                    raise ValueError("Symlink escapes extraction directory")
                links.append({"path": entry.filename, "target": target})
            else:
                ordinary.append(entry)
        # Do not allow an archive member to be written through an archive symlink.
        for link in links:
            if any(e.filename.startswith(link["path"].rstrip("/") + "/")
                   for e in ordinary):
                raise ValueError("Archive member traverses a symlink")
        if archive.testzip() is not None:
            raise ValueError("Archive CRC failed")
        files = []
        for entry in ordinary:
            destination = extracted / entry.filename
            if not destination.resolve().is_relative_to(extracted):
                raise ValueError("Extraction destination escapes private directory")
            if entry.is_dir():
                destination.mkdir(parents=True, exist_ok=True)
                continue
            payload = archive.read(entry)  # zipfile verifies each member CRC.
            destination.parent.mkdir(parents=True, exist_ok=True)
            if destination.exists():
                if destination.is_symlink() or destination.read_bytes() != payload:
                    raise ValueError("Existing extracted member differs")
            else:
                destination.write_bytes(payload)
            files.append({"path": entry.filename, "bytes": len(payload),
                          "sha256": sha256(destination)})
        for link in links:
            destination = extracted / link["path"]
            if destination.is_symlink():
                if os.readlink(destination) != link["target"]:
                    raise ValueError("Existing symlink differs")
            elif destination.exists():
                raise ValueError("Existing non-symlink at symlink path")
            else:
                destination.parent.mkdir(parents=True, exist_ok=True)
                destination.symlink_to(link["target"])
    manifest = {"recorded_at": now.isoformat(), "timezone": "Asia/Saigon",
                "source_kind": "User-supplied local export; production freshness unverified",
                "artifacts": artifacts, "archive_crc": "PASS",
                "regular_files": len(files), "expanded_regular_bytes": sum(f["bytes"] for f in files),
                "symlinks": links, "extracted_sha256_manifest": files}
    manifest_path = backup / "manifest.private.json"
    manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n")
    manifest_path.chmod(0o600)
    public = {key: value for key, value in manifest.items()
              if key != "extracted_sha256_manifest"}
    evidence = root / "docs/phase-0/evidence"
    evidence.mkdir(parents=True, exist_ok=True)
    (evidence / "backup-manifest.json").write_text(
        json.dumps(public, ensure_ascii=False, indent=2) + "\n")
    print(json.dumps(public, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
