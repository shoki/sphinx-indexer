<?php

/* global config */
class Config {
	// debuggingmode
	public $debug = false;

    /* Command to run to index a specific file */
    public $indexerCommand = "nice indexer --config [CONFIG] [INDEX]";

    public $indexDir = "/var/lib/sphinx/index";
	public $transferDir = "/var/lib/sphinx/transfer";
    public $lockdir = "/var/run/sphinx";

	/* transferator settings */
	public $daemonize = true;
	public $pidfile = '/var/run/sphinx/transferator.pid';
	public $userid = '101';
	public $groupid = '103';

	public $sendInterval = 60;	/* start sending when more that N seconds passed and files are available for transfer */
	public $sendCompleteFiles = 4;	/* start sending when at least N files are ready for transfer */

	public $uploadCmd = "uftp -L /dev/null -B 8388608 -R 500000 -A 3 -S 3 -a 500 -s 500 -r 1000 -d 1000 -q -T";

    public $indexFiletypes = array(
    	"spa",
    	"spd",
    	"sph",
    	"spi",
    	"spk",
    	"spm",
    	"spp",
    	"sps", 
    );
}

?>
