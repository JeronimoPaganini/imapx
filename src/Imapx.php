<?php namespace Nahidz\Imapx;

/*
	This library is for laravel 5. If you using laravel 5 then 
	run this command in your terminal

	"composer require nahidz/imapx"
*/
use Illuminate\Support\Facades\Log;
class Imapx
{
	private $driver;
	private $hostname;
	private $username;
	private $password;
	private $ssl;
	private $novalidate;


	protected $isConnect		= 	false;
	protected $stream			= 	'';
	protected $emails			=	'';
	protected $inbox			=	array();
	protected $msgId			=	0;

	protected $sortBy = [
		'order' => [
			'asc' 	=> 0,
			'desc' 	=> 1
		],

		'by'    => [
			'date' 		=> SORTDATE,
			'arrival' 	=> SORTARRIVAL,
			'from' 		=> SORTFROM,
			'subject' 	=> SORTSUBJECT,
			'size'		=> SORTSIZE
		]
	];


	function __construct()
	{
		if(config('imapx.auto-connect')){
			$this->connect();
		}
	}

	/**
	 * @param null $user
	 * @param null $password
	 * @param null $host
	 * @param null $port
	 * @param null $ssl
	 * @param null $driver
	 * @param null $novalidate
	 * @return bool
	 */
	function connect($user = null, $password = null, $host = null, $port = null, $ssl = null, $driver = null, $novalidate = null)
	{
		$this->driver = !is_null($driver) ? $driver : config('imapx.driver');
		$this->hostname = !is_null($host) ? $host : config('imapx.host');
		$this->username = !is_null($user) ? $user : config('imapx.username');
		$this->password = !is_null($password) ? $password : config('imapx.password');
		$this->port = !is_null($port) ? $port : config('imapx.port');
		$this->port = ':'.$this->port;
		$this->ssl = !is_null($ssl) ? $ssl : config('imapx.ssl') ? '/ssl' : '';
		$this->novalidate = !is_null($novalidate) ? $novalidate : config('imapx.novalidate');
		$this->novalidate = $this->novalidate ? '/novalidate-cert' : '';
		try {
			$this->stream = imap_open('{'.$this->hostname.$this->port.'/'.$this->driver.$this->ssl.$this->novalidate.'}INBOX',$this->username,$this->password);
		} catch (\Exception $e) {
			Log::info('Cannot connect to Imap Server: '.$e->getMessage()."\n");
			return false;
		}
		$this->isConnect = true;
	}


	/*
	* close the current connection
	*/
	function close()
	{
		if(!$this->isConnect) return false;
		@imap_close($this->stream);
	}

	/**
	 * @return bool|int
	 */
	public function totalEmail()
	{
		if(!$this->isConnect) return false;

		return imap_num_msg($this->stream);
	}

	/**
	 * @param int $page
	 * @param int $perPage
	 * @param null $sort
	 * @return array|bool
	 */
	public function getInbox($page=1, $perPage=25, $sort=null)
	{
		if(!$this->isConnect) return false;

		$start=$page==1?0:(($page*$perPage)-($perPage-1));
		$order=0;
		$by=SORTDATE;

		if(is_array($sort)){
			$order	= $this->sortBy['order'][$sort[0]];
			$by	= $this->sortBy['by'][$sort[1]];
		}

		$sorted=imap_sort($this->stream, $by, $order);
		$mails = array_chunk($sorted, $perPage);
		if(empty($mails)){
			return false;
		}
		$mails = $mails[$page-1];

		$mbox = imap_check($this->stream);
		$inbox = imap_fetch_overview($this->stream, implode($mails,','), 0);

		if(!is_array($inbox)) return false;

		if(is_array($inbox)){
			$temp_inbox=array();
			foreach($inbox as $msg){
				$temp_inbox[$msg->msgno]=$msg;
			}

			foreach($mails as $msgno){
				$this->inbox[$msgno]=$temp_inbox[$msgno];
			}
		}

		return $this->inbox;
	}

	/**
	 * @param null $id
	 * @return $this|bool
	 */
	function readMail($id=null)
	{
		if(!$this->isConnect) return false;

		if(is_null($id)) return false;

		$this->headers=imap_headerinfo($this->stream, $id);
		$this->msgId=$id;

		return $this;

	}

	/**
	 * @param string $pattern
	 * @return bool|string
	 */
	function getDate($pattern='Y-m-d')
	{
		if(!$this->isConnect) return false;

		$date =date($pattern, strtotime($this->headers->date));
		return $date;
	}

	/**
	 * @return bool
	 */
	function getSubject()
	{
		if(!$this->isConnect) return false;

		return $this->headers->subject;

	}

	/**
	 * @return bool
	 */
	function getRecieverEmail()
	{
		if(!$this->isConnect) return false;

		return $this->headers->toaddress;
	}

	/**
	 * @return bool
	 */
	function getSenderName()
	{
		if(!$this->isConnect) return false;

		$name = $this->headers->senderaddress;
		return $name;
	}

	/**
	 * @return bool|string
	 */
	function getSenderEmail()
	{
		if(!$this->isConnect) return false;

		$mailboxName = $this->headers->sender[0]->mailbox;
		$host 	=	$this->headers->sender[0]->host;

		return $mailboxName.'@'.$host;
	}

	/**
	 * @param string $class
	 * @return bool|string
	 */
	function getSenderLink($class='link')
	{
		if(!$this->isConnect) return false;

		$link = '<a href="mailto:'.$this->getSenderEmail().'" class="'.$class.'">'.$this->getSenderName().'</a>';
		return $link;
	}

	/**
	 * @return bool
	 */
	function isSeen()
	{
		if(!$this->isConnect) return false;

		$seen=$this->headers->Unseen;

		return $seen=='U'?false:true;
	}

	/**
	 * @return bool
	 */
	function isAnswered()
	{
		if(!$this->isConnect) return false;

		$answer = $this->headers->Answered;
		return $answer=='A'?true:false;
	}

	/**
	 * @param string $unit
	 * @return bool|string
	 */
	function getSize($unit='kb')
	{
		if(!$this->isConnect) return false;

		$units=[
			'kb' => 1024,
			'mb' => 1048576
		];

		$size = $this->headers->Size;
		return number_format($size/$units[$unit], 2);
	}

	/**
	 * @param string $display
	 * @param bool $decode
	 * @return bool|string
	 */
	function getBody($display='text', $decode=true)
	{
		if(!$this->isConnect) return false;

		$displayAs=[
			'html'=>2,
			'text'=>1
		];

		if(in_array($displayAs[$display], $displayAs)){
			$display=$displayAs[$display];
		}else{
			return false;
		}

		$body='';

		if($decode){
			$body = quoted_printable_decode(imap_fetchbody($this->stream, $this->msgId, $display));
		}else{
			$body = imap_fetchbody($this->stream, $this->msgId, $display);
		}

		return $body;
	}

	/**
	 * @param $msg_number
	 * @return bool
	 */
	function set_delete_status($msg_number){
		if(!$this->isConnect) return false;
		return imap_delete($this->stream, $msg_number);
	}

	/**
	 * @return bool
	 */
	function delete_trash_messages(){
		if(!$this->isConnect) return false;
		return imap_expunge($this->stream);
	}

	/**
	 * @return bool|object
	 */
	function check(){
		if(!$this->isConnect) return false;
		return imap_mailboxmsginfo($this->stream);
	}

	function __destruct()
	{
		$this->close();
	}

}
