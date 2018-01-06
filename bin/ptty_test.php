<?php

/**
 * Pseudo-TTY Test
 */

$pipePath = '/tmp/emu_pipe';
if (!posix_mkfifo($pipePath, 0666)) {
    //exit(1);
}

$fh = fopen($pipePath, 'r+'); // ensures at least one writer (us) so will be non-blocking
if (!$fh) {
    exit(2);
}
#stream_set_blocking($fh, false); // prevent fread / fwrite blocking

for ($i = 0; $i < 20; ++$i) {
    sleep(1);fwrite($fh, date('Y-m-d H:i:s'));
    sleep(1);fwrite($fh, "\r");
    sleep(1);fwrite($fh, "\n");
}

fclose($fh);
#unlink($pipePath);
