<?php
namespace PHPMailer\PHPMailer;

class PHPMailer
{
    const CHARSET_ISO88591 = 'iso-8859-1';
    const CHARSET_UTF8 = 'utf-8';
    const ENCODING_7BIT = '7bit';
    const ENCODING_8BIT = '8bit';
    const ENCODING_BASE64 = 'base64';
    const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';

    public $Priority = null;
    public $CharSet = self::CHARSET_UTF8;
    public $ContentType = 'text/plain';
    public $Encoding = self::ENCODING_8BIT;
    public $ErrorInfo = '';
    public $From = 'root@localhost';
    public $FromName = 'Root User';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $WordWrap = 0;
    public $Mailer = 'mail';
    public $Sendmail = '/usr/sbin/sendmail';
    public $UseSendmailOptions = true;
    public $ConfirmReadingTo = '';
    public $Hostname = '';
    public $MessageID = '';
    public $MessageDate = '';
    public $Host = 'localhost';
    public $Port = 25;
    public $Helo = '';
    public $SMTPSecure = '';
    public $SMTPAutoTLS = true;
    public $SMTPAuth = false;
    public $SMTPOptions = [];
    public $Username = '';
    public $Password = '';
    public $AuthType = '';
    public $Timeout = 300;
    public $SMTPDebug = 0;
    public $Debugoutput = 'echo';
    public $SMTPKeepAlive = false;
    public $SingleTo = false;
    public $do_verp = false;
    public $AllowEmpty = false;
    public $DKIM_selector = '';
    public $DKIM_identity = '';
    public $DKIM_passphrase = '';
    public $DKIM_domain = '';
    public $DKIM_private = '';
    public $DKIM_private_string = '';
    public $action_function = '';
    public $XMailer = '';
    
    protected $smtp = null;
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $all_recipients = [];
    protected $RecipientsQueue = [];
    protected $ReplyToQueue = [];
    protected $Attachment = [];
    protected $CustomHeader = [];
    protected $lastMessageID = '';
    protected $message_type = '';
    protected $boundary = [];
    protected $language = [];
    protected $error_count = 0;
    protected $sign_cert_file = '';
    protected $sign_key_file = '';
    protected $sign_extracerts_file = '';
    protected $sign_key_pass = '';
    protected $exceptions = false;
    protected $uniqueid = '';

    public function __construct($exceptions = null)
    {
        if (null !== $exceptions) {
            $this->exceptions = (bool)$exceptions;
        }
        $this->Debugoutput = (strpos(PHP_SAPI, 'cli') !== false ? 'echo' : 'html');
    }

    public function isHTML($isHtml = true)
    {
        if ($isHtml) {
            $this->ContentType = 'text/html';
        } else {
            $this->ContentType = 'text/plain';
        }
    }

    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    public function isMail()
    {
        $this->Mailer = 'mail';
    }

    public function addAddress($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('to', $address, $name);
    }

    protected function addOrEnqueueAnAddress($kind, $address, $name)
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        if (!$this->validateAddress($address)) {
            $this->setError('Invalid address: ' . $address);
            if ($this->exceptions) {
                throw new Exception('Invalid address: ' . $address);
            }
            return false;
        }
        if ($kind != 'Reply-To') {
            if (!array_key_exists(strtolower($address), $this->all_recipients)) {
                $this->all_recipients[strtolower($address)] = true;
                array_push($this->$kind, [$address, $name]);
            }
        } else {
            if (!array_key_exists(strtolower($address), $this->ReplyTo)) {
                $this->ReplyTo[strtolower($address)] = [$address, $name];
                array_push($this->ReplyToQueue, [$address, $name]);
            }
        }
        return true;
    }

    public function setFrom($address, $name = '', $auto = true)
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        if (!$this->validateAddress($address)) {
            $this->setError('Invalid address: ' . $address);
            if ($this->exceptions) {
                throw new Exception('Invalid address: ' . $address);
            }
            return false;
        }
        $this->From = $address;
        $this->FromName = $name;
        if ($auto) {
            if (empty($this->Sender)) {
                $this->Sender = $address;
            }
        }
        return true;
    }

    public static function validateAddress($address, $patternselect = null)
    {
        if (null === $patternselect) {
            $patternselect = 'php';
        }
        if (is_callable($patternselect)) {
            return call_user_func($patternselect, $address);
        }
        if (strpos($address, "\n") !== false || strpos($address, "\r") !== false) {
            return false;
        }
        switch ($patternselect) {
            case 'php':
            case 'noregex':
                return (bool)filter_var($address, FILTER_VALIDATE_EMAIL);
            default:
                return (bool)filter_var($address, FILTER_VALIDATE_EMAIL);
        }
    }

    public function send()
    {
        try {
            if (!$this->preSend()) {
                return false;
            }
            return $this->postSend();
        } catch (Exception $e) {
            $this->mailHeader = '';
            $this->setError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
            return false;
        }
    }

    public function preSend()
    {
        if (empty($this->From)) {
            throw new Exception('Missing From address');
        }
        if (count($this->all_recipients) < 1) {
            throw new Exception('Missing recipients');
        }
        
        return true;
    }

    public function postSend()
    {
        try {
            switch ($this->Mailer) {
                case 'smtp':
                    return $this->smtpSend($this->MIMEHeader, $this->MIMEBody);
                case 'mail':
                    return $this->mailSend($this->MIMEHeader, $this->MIMEBody);
                default:
                    return $this->mailSend($this->MIMEHeader, $this->MIMEBody);
            }
        } catch (Exception $e) {
            $this->setError($e->getMessage());
            if ($this->exceptions) {
                throw $e;
            }
        }
        return false;
    }

    protected function smtpSend($header, $body)
    {
        $bad_rcpt = [];
        if (!$this->smtpConnect($this->SMTPOptions)) {
            throw new Exception('SMTP Error: Could not connect to SMTP host.');
        }
        
        if (!$this->smtp->mail($this->Sender)) {
             throw new Exception('SMTP Error: MAIL FROM command failed: ' . $this->smtp->getError()['error']);
        }

        foreach ($this->to as $to) {
            if (!$this->smtp->recipient($to[0])) {
                $bad_rcpt[] = $to[0];
            }
        }

        if (count($bad_rcpt) > 0) {
            // throw new Exception('SMTP Error: The following recipients failed: ' . implode(', ', $bad_rcpt));
        }

        if (!$this->smtp->data($header . $body)) {
            throw new Exception('SMTP Error: Data not accepted.');
        }

        if ($this->SMTPKeepAlive) {
            $this->smtp->reset();
        } else {
            $this->smtp->quit();
            $this->smtp->close();
        }
        
        return true;
    }
    
    public function smtpConnect($options = [])
    {
        if (null === $this->smtp) {
            $this->smtp = new SMTP();
        }
        
        if ($this->smtp->connected()) {
            return true;
        }

        $this->smtp->do_debug = $this->SMTPDebug;
        $this->smtp->Debugoutput = $this->Debugoutput;

        $hosts = explode(';', $this->Host);
        foreach ($hosts as $hostentry) {
            $hostinfo = [];
            if (!preg_match('/^((ssl|tls):\/\/)*([a-zA-Z0-9\.-]+|\[[a-fA-F0-9:]+\]):?([0-9]*)$/', trim($hostentry), $hostinfo)) {
                continue;
            }
            
            $prefix = '';
            $host = $hostinfo[3];
            $port = $this->Port;
            $tls = ($this->SMTPSecure == 'tls');
            $ssl = ($this->SMTPSecure == 'ssl');

            if ($ssl || (substr($host, 0, 6) == 'ssl://')) {
                $prefix = 'ssl://';
                $tls = false;
                $ssl = true;
            } elseif ($tls || (substr($host, 0, 6) == 'tls://')) {
                $tls = true;
                $ssl = false; 
            }
            
            if (!empty($hostinfo[4])) {
                $port = $hostinfo[4];
            }
            
            $tport = (int)$port;
            if ($tport > 0 && $tport < 65536) {
                $port = $tport;
            }

            if ($this->smtp->connect($prefix . $host, $port, $this->Timeout, $options)) {
                if ($tls) {
                    if (!$this->smtp->startTLS()) {
                        return false;
                    }
                }
                if ($this->SMTPAuth) {
                    if (!$this->smtp->authenticate($this->Username, $this->Password, $this->AuthType, '', '', null)) {
                        throw new Exception('SMTP Error: Could not authenticate.');
                    }
                }
                return true;
            }
        }
        return false;
    }
    
    protected function mailSend($header, $body)
    {
        $toArr = [];
        foreach ($this->to as $t) {
            $toArr[] = $this->addrFormat($t);
        }
        $to = implode(', ', $toArr);
        
        $params = null;
        if (!empty($this->Sender) && $this->validateAddress($this->Sender)) {
            $params = sprintf('-f%s', $this->Sender);
        }
        
        if (!empty($this->Sender) && ini_get('safe_mode')) {
             $old_from = ini_get('sendmail_from');
             ini_set('sendmail_from', $this->Sender);
        }
        
        $result = false;
        if ($this->SingleTo && count($toArr) > 1) {
            foreach ($toArr as $val) {
                $result = @mail($val, $this->Subject, $body, $header, $params);
            }
        } else {
            $result = @mail($to, $this->Subject, $body, $header, $params);
        }
        
        if (isset($old_from)) {
            ini_set('sendmail_from', $old_from);
        }
        
        if (!$result) {
            throw new Exception('Could not instantiate mail function.');
        }
        return true;
    }

    public function createHeader()
    {
        $header = [];
        $header[] = 'Date: ' . date('D, j M Y H:i:s O');
        
        if ($this->Mailer != 'mail') {
            $header[] = 'To: ' . implode(', ', array_map([$this, 'addrFormat'], $this->to));
            $header[] = 'From: ' . $this->addrFormat([$this->From, $this->FromName]);
        }
        
        $header[] = 'Subject: ' . $this->Subject;
        $header[] = 'X-Mailer: PHPMailer (Growix Custom)';
        $header[] = 'MIME-Version: 1.0';
        $header[] = 'Content-Type: ' . $this->ContentType . '; charset=' . $this->CharSet;
        $header[] = 'Content-Transfer-Encoding: ' . $this->Encoding;
        
        $this->MIMEHeader = implode(self::LE, $header) . self::LE . self::LE;
        $this->MIMEBody = $this->Body . self::LE . self::LE;
        
        return $this->MIMEHeader;
    }
    
    public function addrFormat($addr)
    {
        if (empty($addr[1])) {
            return $addr[0];
        }
        return '"' . $addr[1] . '" <' . $addr[0] . '>';
    }
    
    public function setError($msg)
    {
        $this->error_count++;
        $this->ErrorInfo = $msg;
    }
}
?>