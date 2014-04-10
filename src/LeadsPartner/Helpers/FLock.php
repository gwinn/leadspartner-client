<?php

namespace LeadsPartner\Helpers;


/**
 * @example
 * <code>
 *         $flock = new FLock(__CLASS__);
 *           if(!$flock->isLocked()){
 *                     $output->writeln("<error> Command ". $this->getName(). " already running in this system. Kill it or try again later </error>", OutputInterface::VERBOSITY_QUIET);
 *           return -1;
 *           }
 * </code>
 *  This class wont work in case of php segfault.
 *
 * topic to discuss: https://github.com/intaro/pinboard/issues/61#issuecomment-35647379
 */
class FLock {

    private $_lock = null;
    private $_lockfilepath = null;
    private $_is_locked = false;
    public function __construct($lock_name, $throw_exception = false) {
        $this->_lockfilepath = "/dev/shm/" . md5($lock_name);
        $this->_lock = fopen($this->_lockfilepath, "w+");
        if ( ! $this->_lock ) {
            throw new \Exception("Could not create lock file for writing "  . $this->_lockfilepath);
        }
        $this->_is_locked = flock($this->_lock, LOCK_NB + LOCK_EX);
        if ($this->_is_locked) {
            ftruncate($this->_lock, 0);
            fwrite($this->_lock, posix_getpid());
            fflush($this->_lock);
        } else if ( $throw_exception ) {
            throw new \Exception("Could not acquire exclusive lock for " . $lock_name);
        }

    }

    public function isLocked() {
        return $this->_is_locked;
    }

    public function __destruct() {
        if ( $this->_lock  && $this->_is_locked ) {
            flock($this->_lock, LOCK_UN);
            unlink($this->_lockfilepath);
        }
    }
}
