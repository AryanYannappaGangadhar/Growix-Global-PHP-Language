<?php
namespace PHPMailer\PHPMailer;

class SMTP
{
    const VERSION = '6.8.0';
    const LE = "\r\n";
    const DEFAULT_PORT = 25;
    const MAX_LINE_LENGTH = 998;
    const MAX_REPLY_LENGTH = 512;
    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;
    const DEBUG_CONNECTION = 3;
    const DEBUG_LOWLEVEL = 4;

    public $do_debug = self::DEBUG_OFF;
    public $Debugoutput = 'echo';
    public $do_verp = false;
    public $Timeout = 300;
    public $Timelimit = 300;
    protected $smtp_conn;
    protected $error = [];
    protected $helo_rply = null;
    protected $server_caps = null;
    protected $last_reply = '';

    protected function edebug($str, $level = 0)
    {
        if ($level > $this->do_debug) {
            return;
        }
        if ($this->Debugoutput instanceof \Psr\Log\LoggerInterface) {
            $this->Debugoutput->debug($str);
            return;
        }
        if (is_callable($this->Debugoutput) && !in_array($this->Debugoutput, ['error_log', 'html', 'echo'])) {
            call_user_func($this->Debugoutput, $str, $level);
            return;
        }
        switch ($this->Debugoutput) {
            case 'error_log':
                error_log($str);
                break;
            case 'html':
                echo gmdate('Y-m-d H:i:s') . ' ' . nl2br(htmlspecialchars($str)) . "<br>\n";
                break;
            case 'echo':
            default:
                $str = preg_replace('/\r\n|\r/ms', "\n", $str);
                echo gmdate('Y-m-d H:i:s') . "\t" . str_replace(
                    "\n",
                    "\n                   \t                  ",
                    trim($str)
                ) . "\n";
                break;
        }
    }

    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        $this->setError('');
        if ($this->connected()) {
            $this->setError('Already connected to a server');
            return false;
        }
        if (empty($port)) {
            $port = self::DEFAULT_PORT;
        }
        $this->edebug("Connection: opening to $host:$port, timeout=$timeout, options=" . var_export($options, true), self::DEBUG_CONNECTION);

        $this->smtp_conn = @stream_socket_client(
            $host . ":" . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create($options)
        );

        if (is_resource($this->smtp_conn)) {
            $this->edebug('Connection: opened', self::DEBUG_CONNECTION);
            if (substr(PHP_OS, 0, 3) != 'WIN') {
                $max = ini_get('max_execution_time');
                if ($max != 0 && $timeout > $max) {
                    @set_time_limit($timeout);
                }
                stream_set_timeout($this->smtp_conn, $timeout, 0);
            }
            $announce = $this->get_lines();
            $this->edebug('SERVER -> CLIENT: ' . $announce, self::DEBUG_SERVER);
            return true;
        }

        $this->setError($host, $errno, $errstr);
        $this->edebug("Connection: Failed to connect to server. Error number: $errno. Error message: $errstr", self::DEBUG_CONNECTION);
        return false;
    }

    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }
        if (!stream_socket_enable_crypto($this->smtp_conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return false;
        }
        return true;
    }

    public function authenticate($username, $password, $authtype = null, $realm = '', $workstation = '', $oauth = null)
    {
        if (!$this->server_caps) {
            $this->setError('Authentication is not allowed at this stage');
            return false;
        }

        if (empty($authtype)) {
            foreach (['LOGIN', 'PLAIN', 'CRAM-MD5'] as $method) {
                if (isset($this->server_caps['AUTH'][$method])) {
                    $authtype = $method;
                    break;
                }
            }
            if (empty($authtype)) {
                $authtype = 'LOGIN';
            }
        }

        switch ($authtype) {
            case 'PLAIN':
                if (!$this->sendCommand('AUTH PLAIN', 'AUTH PLAIN ' . base64_encode("\0" . $username . "\0" . $password), 235)) {
                    return false;
                }
                break;
            case 'LOGIN':
                if (!$this->sendCommand('AUTH LOGIN', 'AUTH LOGIN', 334)) {
                    return false;
                }
                if (!$this->sendCommand('Username', base64_encode($username), 334)) {
                    return false;
                }
                if (!$this->sendCommand('Password', base64_encode($password), 235)) {
                    return false;
                }
                break;
            case 'CRAM-MD5':
                if (!$this->sendCommand('AUTH CRAM-MD5', 'AUTH CRAM-MD5', 334)) {
                    return false;
                }
                $challenge = base64_decode(substr($this->last_reply, 4));
                $response = $username . ' ' . hash_hmac('md5', $challenge, $password);
                if (!$this->sendCommand('Response', base64_encode($response), 235)) {
                    return false;
                }
                break;
            default:
                $this->setError('Authentication method "' . $authtype . '" is not supported');
                return false;
        }

        return true;
    }

    public function connected()
    {
        if (is_resource($this->smtp_conn)) {
            $sock_status = stream_get_meta_data($this->smtp_conn);
            if ($sock_status['eof']) {
                $this->edebug('SMTP NOTICE: EOF caught while checking if connected', self::DEBUG_CLIENT);
                $this->close();
                return false;
            }
            return true;
        }
        return false;
    }

    public function close()
    {
        $this->setError('');
        $this->server_caps = null;
        $this->helo_rply = null;
        if (is_resource($this->smtp_conn)) {
            fclose($this->smtp_conn);
            $this->smtp_conn = null;
            $this->edebug('Connection: closed', self::DEBUG_CONNECTION);
        }
    }

    public function data($msg_data)
    {
        if (!$this->sendCommand('DATA', 'DATA', 354)) {
            return false;
        }
        
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $msg_data));
        $field = substr($lines[0], 0, strpos($lines[0], ':'));
        $in_headers = false;
        if (!empty($field) && strpos($field, ' ') === false) {
            $in_headers = true;
        }

        foreach ($lines as $line) {
            $lines_out = [];
            if ($in_headers && $line === '') {
                $in_headers = false;
            }
            while (strlen($line) > self::MAX_LINE_LENGTH) {
                $pos = strrpos(substr($line, 0, self::MAX_LINE_LENGTH), ' ');
                if (!$pos) {
                    $pos = self::MAX_LINE_LENGTH - 1;
                }
                $lines_out[] = substr($line, 0, $pos);
                $line = substr($line, $pos);
            }
            $lines_out[] = $line;

            foreach ($lines_out as $line_out) {
                if (!empty($line_out) && $line_out[0] === '.') {
                    $line_out = '.' . $line_out;
                }
                fwrite($this->smtp_conn, $line_out . self::LE);
            }
        }

        fwrite($this->smtp_conn, self::LE . '.' . self::LE);

        return $this->get_lines() && substr($this->last_reply, 0, 3) == '250';
    }

    public function hello($host = '')
    {
        if (!$this->sendCommand('EHLO', 'EHLO ' . $host, 250)) {
            if (!$this->sendCommand('HELO', 'HELO ' . $host, 250)) {
                return false;
            }
        }
        return true;
    }

    public function mail($from)
    {
        return $this->sendCommand('MAIL FROM', 'MAIL FROM:<' . $from . '>', 250);
    }

    public function quit($close_on_error = true)
    {
        $ret = $this->sendCommand('QUIT', 'QUIT', 221);
        if ($close_on_error || $ret) {
            $this->close();
        }
        return $ret;
    }

    public function recipient($to)
    {
        return $this->sendCommand('RCPT TO', 'RCPT TO:<' . $to . '>', [250, 251]);
    }

    public function reset()
    {
        return $this->sendCommand('RSET', 'RSET', 250);
    }

    protected function sendCommand($command, $commandstring, $expect)
    {
        if (!$this->connected()) {
            $this->setError("Called $command() without being connected");
            return false;
        }
        $this->edebug("CLIENT -> SERVER: " . $commandstring, self::DEBUG_CLIENT);
        fwrite($this->smtp_conn, $commandstring . self::LE);
        $this->last_reply = $this->get_lines();
        $this->edebug("SERVER -> CLIENT: " . $this->last_reply, self::DEBUG_SERVER);

        $matches = [];
        if (preg_match('/^([0-9]{3})[ -](?:([0-9]\\.[0-9]\\.[0-9]) )?/', $this->last_reply, $matches)) {
            $code = (int)$matches[1];
            $text = substr($this->last_reply, strlen($matches[0]));
        } else {
            $code = (int)substr($this->last_reply, 0, 3);
            $text = substr($this->last_reply, 4);
        }

        if (!in_array($code, (array)$expect)) {
            $this->setError("$command command failed", '', $text);
            return false;
        }

        return true;
    }

    protected function get_lines()
    {
        if (!is_resource($this->smtp_conn)) {
            return '';
        }
        $data = '';
        $endtime = 0;
        stream_set_timeout($this->smtp_conn, $this->Timeout);
        if ($this->Timelimit > 0) {
            $endtime = time() + $this->Timelimit;
        }
        while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
            $str = @fgets($this->smtp_conn, 515);
            $data .= $str;
            if ((isset($str[3]) && $str[3] == ' ')) {
                break;
            }
            $info = stream_get_meta_data($this->smtp_conn);
            if ($info['timed_out']) {
                break;
            }
            if ($endtime && time() > $endtime) {
                break;
            }
        }
        return $data;
    }

    protected function setError($message, $detail = '', $smtp_code = '', $smtp_code_ex = '')
    {
        $this->error = [
            'error' => $message,
            'detail' => $detail,
            'smtp_code' => $smtp_code,
            'smtp_code_ex' => $smtp_code_ex,
        ];
    }
    
    public function getError() {
        return $this->error;
    }
}
?>