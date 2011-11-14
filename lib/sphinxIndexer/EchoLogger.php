<?php

class EchoLogger extends Logger {
	protected function output($msg) {
		printf("%s [%d] %s\n", date('Y-m-d H:i:s'), posix_getpid(), $msg);
	}
}

?>
