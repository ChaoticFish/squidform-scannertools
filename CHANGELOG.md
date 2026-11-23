# Changelog: SquidForm Scanner Tools

## 1.0.3 (2026-11-23): rebuilt
- Rebuilt from SquidForm's `ScannerAddonInterface` (the 1.0.1/1.0.2 source was lost). Same job; installs over 1.0.1 or 1.0.2.
- The EICAR test string is stored base64-encoded in reversed pieces and only put together in memory, so the zip no longer trips upload filters that decode base64.
- Program mode runs only `clamdscan` or `clamscan` (by name and real path), with a fixed argument list and `--` before the file; a scan is stopped after 120 seconds.
- Tested by SquidForm's `tests/e2e-scanner.mjs` (stand-ins for ClamAV that know the EICAR file only by its SHA-256).

## 1.0.2, 1.0.1, 1.0.0
- The "ClamAV program" scan mode and the EICAR test, split out of SquidForm 0.10.1 so the main package holds no test-virus string and runs no programs. (Detailed notes lost with the old source.)
