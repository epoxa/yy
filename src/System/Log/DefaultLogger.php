<?php
namespace YY\System\Log;

use Exception;
use YY\System\Utils;
use YY\System\YY;

class DefaultLogger implements LogInterface
{

    private $map = [
//        'time' => 'profile',
//        'core' => 'debug',
//        'import' => 'import',
//        'system' => 'sys, debug',
//        'debug' => 'debug, screen',
//        'sql' => 'debug',
        'warning' => 'debug, error, screen',
        'error' => 'debug, error, screen',
    ];

    function __construct($init = null)
    {
        if (isset($init['map'])) {
            $this->map = $init['map'];
        }
    }

    static private $disabled = false;
    private $logDir = LOG_DIR;
    private $screenDebugText = '';
    private $started = null;
    private $buffers = [];
    private $profiles = [];
    private $currentProfile = null;
    private $currentProfileStarted = null;

    static public function Disable()
    {
        self::$disabled = true;
    }

    static public function Enable()
    {
        self::$disabled = false;
    }

    public function Log($kind, $message)
    {
        if (self::$disabled) return;
        $now = microtime(true);
        if ($this->started === null) {
            $this->started = $now;
            register_shutdown_function([$this, 'finalize']);
        }
        if (!is_array($kind)) {
            $kind = explode(',', $kind);
        }
        $logs = [];
        foreach ($kind as $k) {
            if (isset($this->map[trim($k)])) {
                $log = $this->map[trim($k)];
                if ($log && !is_array($log)) $log = explode(',', $log);
                if ($log) {
                    foreach ($log as $f) {
                        $logs[trim($f)] = null;
                    }
                }
            }
        }
        foreach ($logs as $log => $dummy) {
            $conditionMethod = $log . 'Check';
            $needWrite = method_exists($this, $conditionMethod) && $this->$conditionMethod();
            if (!$needWrite) continue;
            $writeMethod = $log . 'Write';
            method_exists($this, $writeMethod) && $this->$writeMethod($message);
        }
    }

    public function GetScreenOutput()
    {
        $res = $this->screenDebugText;
        $this->screenDebugText = '';
        return $res;
    }

    public function SetProfile($name)
    {
        if ($name === null) {
            assert($this->currentProfile !== null);
            $this->profiles[$this->currentProfile] += microtime(true) - $this->currentProfileStarted;
            $this->currentProfile = null;
        } else {
            assert($this->currentProfile === null);
            $this->currentProfile = $name;
            if (!isset($this->profiles[$this->currentProfile])) {
                $this->profiles[$this->currentProfile] = 0.0;
            }
            $this->currentProfileStarted = microtime(true);
        }
    }

    public function GetStatistics()
    {
        $kb = round(memory_get_peak_usage(true) / 1024);
        $r = 'max memory: ' . $kb . ' kb';
        if ($this->started !== null) {
            $microseconds = ceil((microtime(true) - $this->started) * 1000);
            $r .= "\n" . 'total time: ' . $microseconds . ' ms';
            foreach ($this->profiles as $profileName => $profileTime) {
                $microseconds = ceil($profileTime * 1000);
                $r .= "\n" . $profileName . ': ' . $microseconds . ' ms';
            }
        }
        return $r;
    }

    public function finalize()
    {
        if (self::$disabled) return;
        // Оптимизация на случай отсутствия протоколов в этом запросе
        if ($this->started === null) return;
        // Сбрасываем на диск буферизованые логи
        $started = date(' - Y-m-d H.i.s', floor($this->started));
        foreach ($this->buffers as $file => $buffer) {
            if (substr($file, 0, 1) === '^') {
                $file = substr($file, 1) . $started;
                $partialFileName = $this->logDir . '/' . $file;
                $f = @fopen($partialFileName . ".txt", 'a');
                $i = 1;
                while ($f === false) {
                    $i++;
                    $f = @fopen($partialFileName . " (" . $i . ").txt", 'a');
                };
            } else {
                $fullFileName = $this->logDir . '/' . $file . '.txt';
                $f = @fopen($fullFileName, 'a');
                while ($f === false) {
                    usleep(rand(10, 100));
                    $f = @fopen($fullFileName, 'a');
                };
            }
            fwrite($f, trim($buffer) . "\n");
            fclose($f);
        }
        // Окончательный отладочный вывод запроса и статистики
        if ($this->debugCheck()) {
            if (!isset(YY::$ME) && function_exists("getallheaders")) {
                $this->directWrite('debug', 'HTTP ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI']);
                $this->directWrite('debug', print_r(getallheaders(), true));
            }
        }
        if ($this->statCheck()) {
            $this->directWrite('debug', '--------------------');
            $this->directWrite('debug', $this->GetStatistics());
            $this->directWrite('debug', '--------------------');
        }
    }

    protected function screenCheck()
    {
        return DEBUG_MODE && DEBUG_ALLOWED_IP;
    }

    protected function screenWrite($message)
    {
        $this->screenDebugText .= $message . PHP_EOL;
        YY::clientExecute("console.log(" . json_encode($message) . ")");
    }

    protected function errorCheck()
    {
        return true; // Могут использоваться при обратной связи пользователей (служба поддержки)
    }

    protected function errorWrite($message)
    {
        $this->directWrite('error', $message);
    }

    protected function gatekeeperCheck()
    {
        return true; // Могут использоваться при обратной связи пользователей (служба поддержки)
    }

    protected function gatekeeperWrite($message)
    {
        $this->directWrite('', $message);
    }

    protected function debugCheck()
    {
        return true; // Пока протоколируем все
//		return isset(YY::$CURRENT_VIEW) || CRON_MODE;
    }

    protected function debugWrite($message)
    {
        $this->directWrite('debug', $message);
    }

    protected function anonymousCheck()
    {
        return !isset(YY::$CURRENT_VIEW);
    }

    protected function anonymousWrite($message)
    {
        $this->directWrite('debug', $message);
    }

    /**
     * @param string $log
     * @param string $message
     *
     * @throws Exception
     */
    protected function directWrite($log, $message)
    {
        static $logFileNames = [];
        if (isset($logFileNames[$log])) {
            $logFileName = $logFileNames[$log];
        } else {
            $dirName = $this->getUserLogDirName();
            if ($dirName === false) return; // Log write cancelled
            $dirName = LOG_DIR . 'users/' . $dirName;
            if ($log) $dirName .= '/' . $log;
            $nativeFsDirName = Utils::ToNativeFilesystemEncoding($dirName);
            if (!file_exists($nativeFsDirName)) {
                mkdir($nativeFsDirName, 0770, true);
            }
            $logFileName = $this->getViewLogFileName();
            $logFileName = $nativeFsDirName . '/' . Utils::toNativeFilesystemEncoding(mb_substr($logFileName, 0, 150));
            $logFileNames[$log] = $logFileName;
        }
//        file_put_contents(LOG_DIR . 'last-debug-name.txt', $logFileName);
        $f = fopen($logFileName, 'a');
        if ($f) {
            fwrite($f, $message . "\n");
            fclose($f);
        } else {
            throw new Exception('Can not open log file (' . $logFileName . ') to write: ' . $message);
        }
    }

    private function bufferedWrite($log, $message)
    {
        if (isset($this->buffers[$log])) {
            $was = $this->buffers[$log];
        } else $was = "";
        $this->buffers[$log] = $was . $message . "\n";
    }

    function statCheck()
    {
        return false;
    }

    /**
     * @return string
     */
    protected function getUserLogDirName()
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * @return string
     */
    protected function getViewLogFileName()
    {
        $logFileName = date('Y-m-d H.i.s', isset(YY::$CURRENT_VIEW, YY::$CURRENT_VIEW['created']) ? YY::$CURRENT_VIEW['created'] : time());
//        if (isset(YY::$CURRENT_VIEW, YY::$CURRENT_VIEW['page'])) {
//            $page = YY::$CURRENT_VIEW['page'];
//            $logFileName .= ' (' . $page['siteName'] . ') ' . $page['title'];
//        }
        $logFileName = preg_replace('/[^\p{L}\d\s\-\_\!\.\()]/u', '', $logFileName);
        return $logFileName;
    }

}
