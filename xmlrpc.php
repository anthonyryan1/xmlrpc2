<?php
require_once( 'util.php' );
require_once( 'settings.php' );

class rXMLRPCCommand 
{
	public $methodName;
	public $params;

	public function __construct( $cmd, $args = null )
	{
		$this->methodName = getCmd($cmd);
		$this->params = array();
		if($args!==null) 
		{
				if(is_array($args))
				foreach($args as $prm)
					$this->addParameter($prm);
			else
				$this->addParameter($args);
		}
	}

	public function addParameters( $args )
	{
		if($args!==null) 
		{
			if(is_array($args))
				foreach($args as $prm)
					$this->addParameter($prm);
			else
				$this->addParameter($args);
		}
	}

	/* This is the only method that isn't perfectly interchangable with the rutorrent's
	rutorrent expects the base64 string to be passed in, we expect the binary data, so
	to use this it's necessary to grep the codebase and correct uses of this */
	public function addParameter( $aValue, $aType = null )
	{
		switch ($aType) {
			case 'i4':
			case 'i8':
				xmlrpc_set_type($aValue, 'int');
				break;
			case 'base64':
				xmlrpc_set_type($aValue, 'base64');
				break;
		}

		$this->params[] = $aValue;
	}
}

class rXMLRPCRequest
{
	protected $commands = array();
	protected $content = "";
	public $i8s = array();
	public $strings = array();
	public $val = array();
	public $fault = false;
	public $parseByTypes = false;
	public $important = true;

	public function __construct( $cmds = null )
	{
		if($cmds)
		{
				if(is_array($cmds))
				foreach($cmds as $cmd)
					$this->addCommand($cmd);
			else
				$this->addCommand($cmds);
		}
	}

	public static function send( $data )
	{
		$data = str_replace('double>','i8>',$data);

		if(LOG_RPC_CALLS)
			toLog($data);
		global $scgi_host;
		global $scgi_port;
		$result = false;
		$contentlength = strlen($data);
		if($contentlength>0)
		{
			$socket = @fsockopen($scgi_host, $scgi_port, $errno, $errstr, RPC_TIME_OUT);
			if($socket) 
			{
				$reqheader =  "CONTENT_LENGTH\x0".$contentlength."\x0"."SCGI\x0"."1\x0";
				$tosend = strlen($reqheader).":{$reqheader},{$data}";
				@fwrite($socket,$tosend,strlen($tosend));
				$result = '';
				while($data = fread($socket, 4096))
					$result .= $data;
				fclose($socket);
			}
		}
		if(LOG_RPC_CALLS)
			toLog($result);


		return $result;
	}

	public function setParseByTypes( $enable = true )
	{
		$this->parseByTypes = $enable;
	}

	public function getCommandsCount()
	{
		return(count($this->commands));
	}

	protected function makeCall()
	{
		$this->fault = false;
		$cnt = count($this->commands);
		switch ($cnt) {
			case 0:
				$this->content = "";
				return false;

			case 1:
				$this->content = xmlrpc_encode_request($this->commands[0]->methodName, $this->commands[0]->params, array('encoding' => 'UTF-8', 'verbosity' => 'no_white_space'));
				return true;

			default:
				$this->content = xmlrpc_encode_request('system.multicall', array($this->commands), array('encoding' => 'UTF-8', 'verbosity' => 'no_white_space'));
				return true;
		}
	}

	public function addCommand( $cmd )
	{
		$this->commands[] = $cmd;
	}

	public function run()
	{
		$ret = false;
		$this->i8s = array();
		$this->strings = array();
		$this->val = array();
		if($this->makeCall())
		{
			$answer = explode("\r\n\r\n", self::send($this->content))[1];
			if(!empty($answer))
			{
				if($this->parseByTypes)
				{
					$decoded = xmlrpc_decode($answer, 'UTF-8');
					$this->val = array();
					if (is_array($decoded)) {
						array_walk_recursive($decoded, function($a) {
							if (is_int($a)) {
								$this->i8s[] = (string)$a;
							} else {
								$this->strings[] = $a;
							}
						});
					} else {
							if (is_int($decoded)) {
								$this->i8s[] = (string)$decoded;
							} else {
								$this->strings[] = $decoded;
							}
					}

					$ret = true;
				}
				else
				{
					/* Because ruTorrent decided they're too good for structured data, we 
					need to flatten the data */

					$decoded = xmlrpc_decode($answer, 'UTF-8');
					$this->val = array();
					if (is_array($decoded)) {
						array_walk_recursive($decoded, function($a) {
							$this->val[] = $a;
						});
					} else {
						$this->val[] = $decoded;
					}

					$ret = true;
				}
				if($ret)
				{
					if(strstr($answer,"faultCode")!==false)
					{
						$this->fault = true;	
						if(LOG_RPC_FAULTS && $this->important)
						{
							toLog($this->content);
							toLog($answer);
						}
					}
				}
			}
		}
		$this->content = "";
		$this->commands = array();
		return($ret);
	}

	public function success()
	{
		return($this->run() && !$this->fault);
	}
}

function getCmd($cmd)
{
	return(rTorrentSettings::get()->getCommand($cmd));
}
