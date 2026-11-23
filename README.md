# SquidForm Scanner Tools (plg_squidform_scannertools) 1.0.3

Optional add-on for [SquidForm](https://github.com/ChaoticFish/squidform) 1.0.0 or later ([all add-ons](https://github.com/ChaoticFish/squidform#add-ons)). It adds:

- **"ClamAV program" scan mode**: uploads are scanned by running `clamdscan` or `clamscan`. Use it when the ClamAV daemon's socket isn't available to PHP. The daemon mode (in SquidForm itself) doesn't need this add-on.
- **The EICAR test** in Components → SquidForm → Check this server: sends the industry-standard harmless test file to your scanner, to prove it catches things.

It's a separate download because a test-virus string and running programs are what many organisations' upload and malware filters block. **Keep it off networks that don't allow those.** SquidForm works without it.

## Install

1. System → Install → Extensions → upload `plg_squidform_scannertools-1.0.3.zip`. It installs over 1.0.1 or 1.0.2.
2. System → Plugins → enable **SquidForm - Scanner Tools**.
3. SquidForm Options → Uploads → choose the scan mode (and, for program mode, the program's path, e.g. `/usr/bin/clamdscan`).

## What it does on the server

- Runs `clamdscan` or `clamscan` only (checked by name and real path) with PHP's `proc_open` and a fixed argument list: `--no-summary --stdout [--fdpass] -- <file>`. No shell; nothing from a form or a visitor; the file is SquidForm's own temporary copy. A scan is stopped after 120 seconds.
- Holds the EICAR test string base64-encoded in reversed pieces and only puts it together in memory. It is never written to the site's folders.

## Test checklist

- [ ] Installs over the old add-on without errors; SquidForm → Check this server says "Scanner add-on: Installed".
- [ ] Daemon mode: "Test with the EICAR file" reports the test file found.
- [ ] Program mode (path set to `clamdscan`): the EICAR test reports it found.
- [ ] Program mode: a normal PDF uploads and is marked clean; a scanner error refuses the upload when "if scanning fails" is Reject.
- [ ] Setting the program to something that isn't `clamdscan`/`clamscan` (e.g. `/bin/sh`) refuses uploads with a scan error.
- [ ] Disable the plugin: the EICAR button is greyed out and Check this server says "Not installed".
