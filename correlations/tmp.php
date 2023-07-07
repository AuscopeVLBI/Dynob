<?php

$s = strtoupper(trim(shell_exec('grep "vdif_" '.$_SERVER['DOCUMENT_ROOT'].'tmp/mf0129/hbbuffer.log | tail -1 | cut -d " " -f 4')));
echo $s;
?>