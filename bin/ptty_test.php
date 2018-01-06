<?php

/**
 * Pseudo-TTY Test
 */

$pipePath = '/tmp/emu_pipe';
#$pipePath = '/dev/pts/2';
//if (!posix_mkfifo($pipePath, 0666)) {
//    //exit(1);
//}

//printf("open %s\n",$pipePath);
//$mode = 'r+';
//$fh = fopen($pipePath, $mode);
//if (!$fh) {
//    exit(2);
//}
//#stream_set_blocking($fh, false); // prevent fread / fwrite blocking
//
//$readHandles = [$fh];
//$writeHandles = [$fh];
//$exceptHandles = [];
//
//printf("loop\n");
//for ($i = 0; $i < 60; ++$i) {
//    sleep(1);
//
//    $handlesChanged = stream_select($readHandles, $writeHandles, $exceptHandles, 0);
//    printf("streams: %d\n", $handlesChanged);
//    if ($handlesChanged) {
//        printf("changed streams: %d %d\n", count($readHandles), count($writeHandles));
//
//        foreach ($readHandles as $readableHandle) {
//            printf(" -> readable\n");
//        }
//        foreach ($writeHandles as $writeableHandle) {
//            //printf(" -> writeable\n");
//            fwrite($writeableHandle, date('Y-m-d H:i:s') . "\r\n");
//        }
//    } else {
//        printf("no changed streams\n");
//    }
//    printf("\n");
//}
//
//printf("close\n");
//fclose($fh);
#unlink($pipePath);

$cmd = 'socat PTY,link=/dev/csTTY1,rawer,wait-slave STDIO';
$fds = [
    0 => ['pipe', 'rb'], // STDIN
    1 => ['pipe', 'wb'], // STDOUT
    2 => ['file', '/tmp/error.log', 'wb'], // STDERR
];
//$env=[];
$pipes = [];

$proc = proc_open($cmd, $fds, $pipes);
//fwrite($pipes[2], "das ist ein test\n");

if (!is_resource($proc)) {
    printf("ERROR: no res\n");
    exit(1);
}

for ($i = 0; $i < 60; ++$i) {
    sleep(1);

    printf("loop %d\n", $i);

    fwrite($pipes[0], date('Y-m-d H:i:s') . "\r\n");

    $readHandles = [$pipes[1]];
    $writeHandles = [];
    $exceptHandles = [];
    $handlesChanged = stream_select($readHandles, $writeHandles, $exceptHandles, 0);
    printf("streams: %d\n", $handlesChanged);

    if ($handlesChanged) {
        printf("changed streams: %d %d\n", count($readHandles), count($writeHandles));

        foreach ($readHandles as $readableHandle) {
            printf(" -> readable\n");
            //$data=stream_socket_recvfrom($readableHandle, 2048);
            $maxLen = 2048;
            $data = fread($readableHandle, 2048);

            for ($i = 0; $i < $maxLen; ++$i) {
                if (!isset($data[$i])) {
                    printf(" -> break\n");
                    break;
                }
                $c = $data[$i];
                printf(" -> %x\n", ord($c));
            }
        }
    }
}

foreach ($pipes as $pipe) {
    fclose($pipe);
}
proc_close($proc);

printf("end\n");
