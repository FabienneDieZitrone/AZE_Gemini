#!/usr/bin/env python3
import os
import ssl
import sys
import time
import ftplib
from pathlib import Path


def load_env(path: str = ".env") -> None:
    if not os.path.exists(path):
        return
    try:
        with open(path, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                k, v = line.split("=", 1)
                if k and k not in os.environ:
                    os.environ[k.strip()] = v.strip()
    except Exception:
        pass


def ensure_dir(path: Path) -> None:
    path.mkdir(parents=True, exist_ok=True)


def connect_ftps(host: str, user: str, password: str) -> ftplib.FTP_TLS:
    context = ssl.create_default_context()
    ftps = ftplib.FTP_TLS(context=context)
    ftps.connect(host, 21, timeout=30)
    ftps.login(user, password)
    ftps.prot_p()  # protect data channel
    return ftps


def list_dir(ftps: ftplib.FTP_TLS, path: str):
    # Prefer MLSD if available
    entries = []
    try:
        ftps.cwd(path)
    except Exception:
        return entries

    try:
        for name, facts in ftps.mlsd():
            entries.append((name, facts.get('type', 'file')))
        return entries
    except Exception:
        pass

    lines = []
    ftps.retrlines('LIST', lines.append)
    for line in lines:
        parts = line.split(maxsplit=8)
        if not parts:
            continue
        name = parts[-1]
        is_dir = parts[0].startswith('d')
        entries.append((name, 'dir' if is_dir else 'file'))
    return entries


def download_recursive(ftps: ftplib.FTP_TLS, remote_root: str, local_root: Path, max_depth: int = 50):
    if max_depth < 0:
        return
    ensure_dir(local_root)
    for name, typ in list_dir(ftps, remote_root):
        if name in (".", ".."):
            continue
        rpath = remote_root.rstrip('/') + '/' + name if remote_root != '/' else '/' + name
        lpath = local_root / name
        if typ == 'dir':
            download_recursive(ftps, rpath, lpath, max_depth - 1)
        else:
            # Download file
            with open(lpath, 'wb') as f:
                ftps.retrbinary(f'RETR {name}' if remote_root == '.' else f'RETR {rpath}', f.write)


def main():
    load_env()
    host = os.getenv('FTP_HOST') or os.getenv('FTP_SERVER')
    user = os.getenv('FTP_USER')
    pwd = os.getenv('FTP_PASS') or os.getenv('FTP_PASSWORD')
    start_path = os.getenv('FTP_BACKUP_START', '/')
    out_dir = Path(os.getenv('FTP_BACKUP_DIR', 'backups'))
    ts = time.strftime('%Y%m%d_%H%M%S')
    snapshot_dir = out_dir / f'webspace_{ts}'

    if not host or not user or not pwd:
        print('ERROR: Missing FTP credentials (FTP_HOST/FTP_SERVER, FTP_USER, FTP_PASS/FTP_PASSWORD)', file=sys.stderr)
        sys.exit(1)

    print(f"Connecting to {host} as {user} ...")
    ftps = connect_ftps(host, user, pwd)
    print("Connected. Starting download …")

    # Change CWD to start path and then recurse
    try:
        ftps.cwd(start_path)
        remote_root = start_path
    except Exception:
        print(f"WARN: Could not CWD to {start_path}, falling back to /", file=sys.stderr)
        ftps.cwd('/')
        remote_root = '/'

    download_recursive(ftps, remote_root, snapshot_dir)
    ftps.quit()
    print(f"Backup complete → {snapshot_dir}")


if __name__ == '__main__':
    main()

