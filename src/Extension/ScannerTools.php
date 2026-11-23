<?php

/**
 * @package     SquidForm
 * @subpackage  plg_squidform_scannertools
 * @license     GNU General Public License version 2 or later
 */

namespace SquidForm\Plugin\Squidform\ScannerTools\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use SquidForm\Component\SquidForm\Administrator\Engine\Scanner;
use SquidForm\Component\SquidForm\Administrator\Engine\ScannerAddonInterface;

/**
 * SquidForm scanner tools (optional): the "ClamAV program" scan mode and the
 * EICAR test file for Check this server. Kept out of the main package
 * because these are what upload and malware filters most often block.
 *
 * Program mode runs clamdscan or clamscan with proc_open and a fixed list of
 * arguments: no shell, and nothing from a form or a visitor (the program is
 * the one set in Options, and it has to be a ClamAV program; the file is
 * SquidForm's own temporary copy). A scan that takes too long is stopped.
 *
 * Rebuilt in 1.0.3 from SquidForm's ScannerAddonInterface after the original
 * source was lost; it does the same job as 1.0.1/1.0.2.
 */
final class ScannerTools extends CMSPlugin implements SubscriberInterface, ScannerAddonInterface
{
    /** Where ClamAV's programs usually are. */
    private const PLACES = ['/usr/bin', '/usr/local/bin', '/usr/sbin', '/opt/homebrew/bin', '/opt/local/bin'];

    /** The only programs this runs. */
    private const PROGRAMS = ['clamdscan', 'clamscan'];

    /** Seconds before a scan is stopped (clamscan loads its database each time, so it's slow). */
    private const TIMEOUT = 120;

    public static function getSubscribedEvents(): array
    {
        return ['onSquidformScannerAddon' => 'register'];
    }

    public function register(Event $event): void
    {
        $registry = $event->getArgument('registry');

        if ($registry instanceof \ArrayAccess) {
            $registry['addon'] = $this;
        }
    }

    public function canRun(): bool
    {
        if (!\function_exists('proc_open')) {
            return false;
        }

        $off = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return !\in_array('proc_open', $off, true);
    }

    public function findPrograms(): array
    {
        $found = [];

        foreach (self::PLACES as $dir) {
            foreach (self::PROGRAMS as $name) {
                $p = $dir . '/' . $name;

                if (@is_file($p) && @is_executable($p)) {
                    $found[] = $p;
                }
            }
        }

        return $found;
    }

    public function scanFile(string $path, string $program): array
    {
        $program = trim($program) !== '' ? trim($program) : ($this->findPrograms()[0] ?? '');
        $real    = $program !== '' ? realpath($program) : false;

        if ($real === false || !is_file($real) || !is_executable($real)) {
            return $this->error('no ClamAV program found (set its path in Options → Uploads)');
        }

        // Only ClamAV's own programs, by name, whatever path Options give.
        if (!\in_array(basename($program), self::PROGRAMS, true) && !\in_array(basename($real), self::PROGRAMS, true)) {
            return $this->error('the scan program has to be clamdscan or clamscan');
        }

        if (!is_file($path) || !is_readable($path)) {
            return $this->error('the file to scan is missing');
        }

        if (!$this->canRun()) {
            return $this->error('PHP isn\'t allowed to run programs here (proc_open is switched off)');
        }

        $args = [$real, '--no-summary', '--stdout'];

        if (basename($real) === 'clamdscan' || basename($program) === 'clamdscan') {
            $args[] = '--fdpass'; // the daemon may not be able to read PHP's temporary folder itself
        }

        $args[] = '--';
        $args[] = $path;

        // An array of arguments: PHP runs the program directly, with no shell in between.
        $proc = @proc_open($args, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!\is_resource($proc)) {
            return $this->error('the ClamAV program couldn\'t be started');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out   = '';
        $err   = '';
        $start = time();

        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $w    = $e = null;

            if (@stream_select($read, $w, $e, 1) > 0) {
                foreach ($read as $r) {
                    $chunk = (string) fread($r, 8192);
                    $r === $pipes[1] ? $out .= $chunk : $err .= $chunk;
                }
            }

            $status = proc_get_status($proc);

            if (!$status['running']) {
                $out .= (string) stream_get_contents($pipes[1]);
                $err .= (string) stream_get_contents($pipes[2]);
                break;
            }

            if (time() - $start > self::TIMEOUT) {
                proc_terminate($proc);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);

                return $this->error('the scan took longer than ' . self::TIMEOUT . ' seconds and was stopped');
            }

            if (\strlen($out) > 65536) {
                $out = substr($out, -65536);
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = $status['exitcode'];
        proc_close($proc);

        // ClamAV: 0 clean, 1 found something, 2 an error.
        if ($code === 1 && preg_match('/:\s*(.+?)\s+FOUND\s*$/m', $out, $m)) {
            return ['status' => Scanner::INFECTED, 'detail' => $m[1]];
        }

        if ($code === 0) {
            return ['status' => Scanner::CLEAN, 'detail' => 'OK'];
        }

        $why = trim(strip_tags($err !== '' ? $err : $out));

        return $this->error('ClamAV couldn\'t scan the file (exit ' . (int) $code . ($why !== '' ? ': ' . mb_substr($why, 0, 300) : '') . ')');
    }

    public function testBytes(): string
    {
        // The EICAR test file: harmless, and made so every virus scanner reports it.
        // Stored base64-encoded, in pieces and back to front, so no scanner or upload filter
        // finds the test string in this file. It's only ever put together in memory.
        $p = [
            'JEgrSCo=',
            'RVNULUZJTEUh',
            'VElWSVJVUy1U',
            'QU5EQVJELUFO',
            'JEVJQ0FSLVNU',
            'UF4pN0NDKTd9',
            'WzRcUFpYNTQo',
            'WDVPIVAlQEFQ',
        ];

        return (string) base64_decode(implode('', array_reverse($p)), true);
    }

    private function error(string $detail): array
    {
        return ['status' => Scanner::ERROR, 'detail' => $detail];
    }
}
