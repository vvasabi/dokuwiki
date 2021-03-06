<?php
/**
 * TAR format class - Creates TAR archives
 *
 * This class is part or the MaxgComp suite and originally named
 * MaxgTar class.
 *
 * Modified for Dokuwiki
 *
 * @license LGPL-2.1
 * @link    http://docs.maxg.info
 * @author  Bouchon <tarlib@bouchon.org> (Maxg)
 * @author  Christopher Smith <chris@jalakai.co.uk>
 */

/**
 * Those constants represent the compression method to use.
 * COMPRESS_GZIP is used for the GZIP compression; COMPRESS_BZIP for
 * BZIP2 and COMPRESS_NONE for no compression.
 *
 * On the other hand, COMPRESS_AUTO is a bit harder. It will first check
 * if the zlib extensions are loaded.
 * If it is, GZIP will be used. Else it will check if the bz2 extensions
 * are loaded. If it is, BZIP2 will be used. Else no compression will be
 * performed.
 *
 * You can then use getCompression() to know the compression chosen.
 *
 * If you selected a compression which can't be used (i.e extension not
 * present), it will be just disabled, and won't produce any error !
 * As a consequence, getCompression() will return COMPRESS_NONE
 *
 * ARCHIVE_DYNAMIC can be passed as the first argument of the constructor, to
 * create an archive in memory instead of a file. See also: MaxgTar(),
 * getDynamicArchive() and writeArchive()
 *
 * ARCHIVE_RENAMECOMP is a flag that can be multiplied by the compression method
 * (i.e COMPRESS_AUTO * ARCHIVE_RENAMECOMP). This will add the correct extension
 * to the archive name, which is useful with COMPRESS_AUTO, ie .bz2 if you gave
 * COMPRESS_BZIP. See also getCompression(TRUE) which does exactly the
 * same
 *
 * COMPRESS_DETECT does exactly the opposite and try to detect the
 * compression to use to read the archive depending on its extension. (i.e if
 * the archive ends with .tar.gz TarLib will try to decompress it with
 * GZIP). See also setCompression()
 *
 * FULL_ARCHIVE is a -1 constant that means "the complete archive" when
 * extracting. This is explained in Extract()
 */
#define('COMPRESS_GZIP',1);
#define('COMPRESS_BZIP',2);
#define('COMPRESS_AUTO',3);
#define('COMPRESS_NONE',0);
#define('TARLIB_VERSION','1.2');
#define('FULL_ARCHIVE',-1);
#define('ARCHIVE_DYNAMIC',0);
#define('ARCHIVE_RENAMECOMP',5);
#define('COMPRESS_DETECT',-1);

class TarLib {
    var $_comptype;
    var $_compzlevel;
    var $_fp;
    var $_memdat;
    var $_nomf;
    var $_result;
    var $_initerror;

    const   COMPRESS_GZIP      = 1;
    const   COMPRESS_BZIP      = 2;
    const   COMPRESS_AUTO      = 3;
    const   COMPRESS_NONE      = 0;
    const   TARLIB_VERSION     = '1.2';
    const   FULL_ARCHIVE       = -1;
    const   ARCHIVE_DYNAMIC    = 0;
    const   ARCHIVE_RENAMECOMP = 5;
    const   COMPRESS_DETECT    = -1;

    /**
     * constructor, initialize the class
     *
     * The constructor initialize the variables and prepare the class for the
     * archive, and return the object created. Note that you can use multiple
     * instances of the MaxgTar class, if you call this function another time and
     * store the object in an other variable.
     *
     * In fact, MaxgTar accepts the following arguments (all are optional) :
     *
     * filename can be either a file name (absolute or relative). In this
     * case, it can be used both for reading and writing. You can also open
     * remote archive if you add a protocole name at the beginning of the file
     * (ie https://host.dom/archive.tar.gz), but for reading only and if the
     * directive allow_url_fopen is enabled in PHP.INI (this can be checked with
     * TarInfo()). If you pass a file that doesn't exist, the script
     * will try to create it. If the archive already exists and contains files,
     * you can use Add() to append files.But by default this parameter
     * is ARCHIVE_DYNAMIC (write only) so the archive is created in memory and
     * can be sent to a file [writeArchive()] or to the client
     * [sendClient()]
     *
     * compression_type should be a constant that represents a type of
     * compression, or its integer value. The different values are described in
     * the constants.
     *
     * compression_level is an integer between 1 and 9 (by default) an
     * represent the GZIP or BZIP compression level.  1 produce fast compression,
     * and 9 produce smaller files. See the RFC 1952 for more infos.
     */
    function __construct($p_filen = TarLib::ARCHIVE_DYNAMIC, $p_comptype = TarLib::COMPRESS_AUTO, $p_complevel = 9) {
        $this->_initerror = 0;
        $this->_nomf      = $p_filen;
        $flag             = 0;
        if($p_comptype && $p_comptype % 5 == 0) {
            $p_comptype /= TarLib::ARCHIVE_RENAMECOMP;
            $flag = 1;
        }

        if($p_complevel > 0 && $p_complevel <= 9) $this->_compzlevel = $p_complevel;
        else $this->_compzlevel = 9;

        if($p_comptype == TarLib::COMPRESS_DETECT) {
            if(strtolower(substr($p_filen, -3)) == '.gz') $p_comptype = TarLib::COMPRESS_GZIP;
            elseif(strtolower(substr($p_filen, -4)) == '.bz2') $p_comptype = TarLib::COMPRESS_BZIP;
            else $p_comptype = TarLib::COMPRESS_NONE;
        }

        switch($p_comptype) {
            case TarLib::COMPRESS_GZIP:
                if(!extension_loaded('zlib')) $this->_initerror = -1;
                $this->_comptype = TarLib::COMPRESS_GZIP;
                break;

            case TarLib::COMPRESS_BZIP:
                if(!extension_loaded('bz2')) $this->_initerror = -2;
                $this->_comptype = TarLib::COMPRESS_BZIP;
                break;

            case TarLib::COMPRESS_AUTO:
                if(extension_loaded('zlib'))
                    $this->_comptype = TarLib::COMPRESS_GZIP;
                elseif(extension_loaded('bz2'))
                    $this->_comptype = TarLib::COMPRESS_BZIP;
                else
                    $this->_comptype = TarLib::COMPRESS_NONE;
                break;

            default:
                $this->_comptype = TarLib::COMPRESS_NONE;
        }

        if($this->_initerror < 0) $this->_comptype = TarLib::COMPRESS_NONE;

        if($flag) $this->_nomf .= '.'.$this->getCompression(1);
        $this->_result = true;
    }

    /**
     * Recycle a TAR object.
     *
     * This function does exactly the same as TarLib (constructor), except it
     * returns a status code.
     */
    function setArchive($p_name = '', $p_comp = TarLib::COMPRESS_AUTO, $p_level = 9) {
        $this->_CompTar();
        $this->__construct($p_name, $p_comp, $p_level);
        return $this->_result;
    }

    /**
     * Get the compression used to generate the archive
     *
     * This is a very useful function when you're using dynamical archives.
     * Besides, if you let the script chose which compression to use, you'll have
     * a problem when you'll want to send it to the client if you don't know
     * which compression was used.
     *
     * There are two ways to call this function : if you call it without argument
     * or with FALSE, it will return the compression constants, explained on the
     * MaxgTar Constants.  If you call it with GetExtension on TRUE it will
     * return the extension without starting dot (ie "tar" or "tar.bz2" or
     * "tar.gz")
     *
     * NOTE: This can be done with the flag ARCHIVE_RENAMECOMP, see the
     * MaxgTar Constants
     */
    function getCompression($ext = false) {
        $exts = Array('tar', 'tar.gz', 'tar.bz2');
        if($ext) return $exts[$this->_comptype];
        return $this->_comptype;
    }

    /**
     * Change the compression mode.
     *
     * This function will change the compression methode to read or write
     * the archive. See the MaxgTar Constants to see which constants you can use.
     * It may look strange, but it returns the GZIP compression level.
     */
    function setCompression($p_comp = TarLib::COMPRESS_AUTO) {
        $this->setArchive($this->_nomf, $p_comp, $this->_compzlevel);
        return $this->_compzlevel;
    }

    /**
     * Returns the compressed dynamic archive.
     *
     * When you're working with dynamic archives, use this function to grab
     * the final compressed archive in a string ready to be put in a SQL table or
     * in a file.
     */
    function getDynamicArchive() {
        return $this->_encode($this->_memdat);
    }

    /**
     * Write a dynamical archive into a file
     *
     * This function attempts to write a dynamicaly-genrated archive into
     * TargetFile on the webserver.  It returns a TarErrorStr() status
     * code.
     *
     * To know the extension to add to the file if you're using AUTO_DETECT
     * compression, you can use getCompression().
     */
    function writeArchive($p_archive) {
        if(!$this->_memdat) return -7;
        $fp = @fopen($p_archive, 'wb');
        if(!$fp) return -6;

        fwrite($fp, $this->_memdat);
        fclose($fp);

        return true;
    }

    /**
     * Send a TAR archive to the client browser.
     *
     * This function will send an archive to the client, and return a status
     * code, but can behave differently depending on the arguments you give. All
     * arguments are optional.
     *
     * ClientName is used to specify the archive name to give to the browser. If
     * you don't give one, it will send the constructor filename or return an
     * error code in case of dynamical archive.
     *
     * FileName is optional and used to send a specific archive. Leave it blank
     * to send dynamical archives or the current working archive.
     *
     * If SendHeaders is enabled (by default), the library will send the HTTP
     * headers itself before it sends the contents. This headers are :
     * Content-Type, Content-Disposition, Content-Length and Accept-Range.
     *
     * Please note that this function DOES NOT stops the script so don't forget
     * to exit() to avoid your script sending other data and corrupt the archive.
     * Another note : for AUTO_DETECT dynamical archives you can know the
     * extension to add to the name with getCompression()
     */
    function sendClient($name = '', $archive = '', $headers = true) {
        if(!$name && !$this->_nomf) return -9;
        if(!$archive && !$this->_memdat) return -10;
        if(!$name) $name = utf8_basename($this->_nomf);

        if($archive) {
            if(!file_exists($archive)) return -11;
        }
        $decoded = $this->getDynamicArchive();

        if($headers) {
            header('Content-Type: application/x-gtar');
            header('Content-Disposition: attachment; filename='.utf8_basename($name));
            header('Accept-Ranges: bytes');
            header('Content-Length: '.($archive ? filesize($archive) : strlen($decoded)));
        }

        if($archive) {
            $fp = @fopen($archive, 'rb');
            if(!$fp) return -4;

            while(!feof($fp)) echo fread($fp, 2048);
        } else {
            echo $decoded;
        }

        return true;
    }

    /**
     * Extract part or totality of the archive.
     *
     * This function can extract files from an archive, and returns then a
     * status codes that can be converted with TarErrorStr() into a
     * human readable message.
     *
     * Only the first argument is required, What and it can be either the
     * constant FULL_ARCHIVE or an indexed array containing each file you want to
     * extract.
     *
     * To contains the target folder to extract the archive. It is optional and
     * the default value is '.' which means the current folder. If the target
     * folder doesn't exist, the script attempts to create it and give it
     * permissions 0777 by default.
     *
     * RemovePath is very usefull when you want to extract files from a subfoler
     * in the archive to a root folder. For instance, if you have a file in the
     * archive called some/sub/folder/test.txt and you want to extract it to the
     * script folder, you can call Extract with To = '.' and RemovePath =
     * 'some/sub/folder/'
     *
     * FileMode is optional and its default value is 0755. It is in fact the UNIX
     * permission in octal mode (prefixed with a 0) that will be given on each
     * extracted file.
     */
    function Extract($p_what = TarLib::FULL_ARCHIVE, $p_to = '.', $p_remdir = '', $p_mode = 0755) {
        if(!$this->_OpenRead()) return -4;
        //  if(!@is_dir($p_to)) if(!@mkdir($p_to, 0777)) return -8;   --CS
        if(!@is_dir($p_to)) if(!$this->_dirApp($p_to)) return -8; //--CS (route through correct dir fn)

        $ok = $this->_extractList($p_to, $p_what, $p_remdir, $p_mode);
        $this->_CompTar();

        return $ok;
    }

    /**
     * Create a new package with the given files
     *
     * This function will attempt to create a new archive with global headers
     * then add the given files into.  If the archive is a real file, the
     * contents are written directly into the file. If it is a dynamic archive,
     * contents are only stored in memory. This function should not be used to
     * add files to an existing archive, you should use Add() instead.
     *
     * The FileList actually supports three different modes:
     *
     * - You can pass a string containing filenames separated by pipes '|'.
     *   In this case thes file are read from the filesystem and the root folder
     *   is the folder running script located. NOT RECOMMENDED
     *
     * - You can also give an indexed array containing the filenames. The
     *   behaviour for the content reading is the same as above.
     *
     * - You can pass an array of arrays. For each file use an array where the
     *   first element contains the filename and the second contains the file
     *   contents. You can even add empty folders to the package if the filename
     *   has a leading '/'. Once again, have a look at the exemples to understand
     *   better.
     *
     * Note you can also give arrays with both dynamic contents and static files.
     *
     * The optional parameter RemovePath can be used to delete a part of the tree
     * of the filename you're adding, for instance if you're adding in the root
     * of a package a file that is stored somewhere in the server tree.
     *
     * On the contrary the parameter AddPath can be used to add a prefix folder
     * to the file you store. Note also that the RemovePath is applied before the
     * AddPath is added, so it HAS a sense to use both parameters together.
     */
    function Create($p_filelist, $p_add = '', $p_rem = '') {
        if(!$fl = $this->_fetchFilelist($p_filelist)) return -5;
        if(!$this->_OpenWrite()) return -6;

        $ok = $this->_addFileList($fl, $p_add, $p_rem);

        if($ok) {
            $this->_writeFooter();
        } else {
            $this->_CompTar();
            @unlink($this->_nomf);
        }

        return $ok;
    }

    /**
     * Add files to an existing package.
     *
     * This function does exactly the same as Create() exept it
     * will append the given files at the end of the archive.
     *
     * Note: This is only supported for dynamic in memory files and uncompressed
     *       tar files
     *
     * This function returns a status code, you can use TarErrorStr() on
     * it to get the human-readable description of the error.
     */
    function Add($p_filelist, $p_add = '', $p_rem = '') {
        if($this->_nomf !== TarLib::ARCHIVE_DYNAMIC &&
            $this->_comptype !== TarLib::COMPRESS_NONE
        ) {
            return -12;
        }

        if(($this->_nomf !== TarLib::ARCHIVE_DYNAMIC && !$this->_fp) ||
            ($this->_nomf === TarLib::ARCHIVE_DYNAMIC && !$this->_memdat)
        ) {
            return $this->Create($p_filelist, $p_add, $p_rem);
        }

        if(!$fl = $this->_fetchFilelist($p_filelist)) return -5;
        return $this->_append($fl, $p_add, $p_rem);
    }

    /**
     * Read the contents of a TAR archive
     *
     * This function attempts to get the list of the files stored in the
     * archive, and return either an error code or an indexed array of
     * associative array containing for each file the following information :
     *
     * checksum    Tar Checksum of the file
     * filename    The full name of the stored file (up to 100 c.)
     * mode        UNIX permissions in DECIMAL, not octal
     * uid         The Owner ID
     * gid         The Group ID
     * size        Uncompressed filesize
     * mtime       Timestamp of last modification
     * typeflag    Empty for files, set for folders
     * link        For the links, did you guess it ?
     * uname       Owner name
     * gname       Group name
     */
    function ListContents() {
        if(!$this->_nomf) return -3;
        if(!$this->_OpenRead()) return -4;

        $result = Array();

        while($dat = $this->_read(512)) {
            $dat = $this->_readHeader($dat);
            if(!is_array($dat)) continue;

            $this->_seek(ceil($dat['size'] / 512) * 512, 1);
            $result[] = $dat;
        }

        return $result;
    }

    /**
     * Convert a status code into a human readable message
     *
     * Some MaxgTar functions like Create(), Add() ... return numerical
     * status code.  You can pass them to this function to grab their english
     * equivalent.
     */
    function TarErrorStr($i) {
        $ecodes = Array(
            1   => true,
            0   => "Undocumented error",
            -1  => "Can't use COMPRESS_GZIP compression : ZLIB extensions are not loaded !",
            -2  => "Can't use COMPRESS_BZIP compression : BZ2 extensions are not loaded !",
            -3  => "You must set a archive file to read the contents !",
            -4  => "Can't open the archive file for read !",
            -5  => "Invalide file list !",
            -6  => "Can't open the archive in write mode !",
            -7  => "There is no ARCHIVE_DYNAMIC to write !",
            -8  => "Can't create the directory to extract files !",
            -9  => "Please pass a archive name to send if you made created an ARCHIVE_DYNAMIC !",
            -10 => "You didn't pass an archive filename and there is no stored ARCHIVE_DYNAMIC !",
            -11 => "Given archive doesn't exist !",
            -12 => "Appending not supported for compressed files"
        );

        return isset($ecodes[$i]) ? $ecodes[$i] : $ecodes[0];
    }

    /**
     * Seek in the data stream
     *
     * @todo  probably broken for bzip tars
     * @param int  $p_flen seek to this position
     * @param bool $tell  seek from current position?
     */
    function _seek($p_flen, $tell = false) {
        if($this->_nomf === TarLib::ARCHIVE_DYNAMIC)
            $this->_memdat = substr($this->_memdat, 0, ($tell ? strlen($this->_memdat) : 0) + $p_flen);
        elseif($this->_comptype == TarLib::COMPRESS_GZIP)
            @gzseek($this->_fp, ($tell ? @gztell($this->_fp) : 0) + $p_flen);
        elseif($this->_comptype == TarLib::COMPRESS_BZIP)
            @fseek($this->_fp, ($tell ? @ftell($this->_fp) : 0) + $p_flen);
        else
            @fseek($this->_fp, ($tell ? @ftell($this->_fp) : 0) + $p_flen);
    }

    /**
     * Open the archive for reading
     *
     * @return bool true if succesfull
     */
    function _OpenRead() {
        if($this->_comptype == TarLib::COMPRESS_GZIP)
            $this->_fp = @gzopen($this->_nomf, 'rb');
        elseif($this->_comptype == TarLib::COMPRESS_BZIP)
            $this->_fp = @bzopen($this->_nomf, 'rb');
        else
            $this->_fp = @fopen($this->_nomf, 'rb');

        return ($this->_fp ? true : false);
    }

    /**
     * Open the archive for writing
     *
     * @param string $add filemode
     * @return bool true on success
     */
    function _OpenWrite($add = 'w') {
        if($this->_nomf === TarLib::ARCHIVE_DYNAMIC) return true;

        if($this->_comptype == TarLib::COMPRESS_GZIP)
            $this->_fp = @gzopen($this->_nomf, $add.'b'.$this->_compzlevel);
        elseif($this->_comptype == TarLib::COMPRESS_BZIP)
            $this->_fp = @bzopen($this->_nomf, $add.'b');
        else
            $this->_fp = @fopen($this->_nomf, $add.'b');

        return ($this->_fp ? true : false);
    }

    /**
     * Closes the open file pointer
     */
    function _CompTar() {
        if($this->_nomf === TarLib::ARCHIVE_DYNAMIC || !$this->_fp) return;

        if($this->_comptype == TarLib::COMPRESS_GZIP) @gzclose($this->_fp);
        elseif($this->_comptype == TarLib::COMPRESS_BZIP) @bzclose($this->_fp);
        else @fclose($this->_fp);
    }

    /**
     * Read from the open file pointer
     *
     * @param int $p_len bytes to read
     * @return string
     */
    function _read($p_len) {
        if($this->_comptype == TarLib::COMPRESS_GZIP)
            return @gzread($this->_fp, $p_len);
        elseif($this->_comptype == TarLib::COMPRESS_BZIP)
            return @bzread($this->_fp, $p_len);
        else
            return @fread($this->_fp, $p_len);
    }

    /**
     * Write to the open filepointer or memory
     *
     * @param string $p_data
     * @return int
     */
    function _write($p_data) {
        if($this->_nomf === TarLib::ARCHIVE_DYNAMIC) {
            $this->_memdat .= $p_data;
            return strlen($p_data);
        } elseif($this->_comptype == TarLib::COMPRESS_GZIP) {
            return @gzwrite($this->_fp, $p_data);
        } elseif($this->_comptype == TarLib::COMPRESS_BZIP) {
            return @bzwrite($this->_fp, $p_data);
        } else {
            return @fwrite($this->_fp, $p_data);
        }
    }

    /**
     * Compress given data according to the set compression method
     *
     * @param $p_dat
     * @return string
     */
    function _encode($p_dat) {
        if($this->_comptype == TarLib::COMPRESS_GZIP)
            return gzencode($p_dat, $this->_compzlevel);
        elseif($this->_comptype == TarLib::COMPRESS_BZIP)
            return bzcompress($p_dat, $this->_compzlevel);
        else return $p_dat;
    }

    /**
     * Decode the given tar file header
     *
     * @param $p_dat
     * @return array|bool
     */
    function _readHeader($p_dat) {
        if(!$p_dat || strlen($p_dat) != 512) return false;

        for($i = 0, $chks = 0; $i < 148; $i++)
            $chks += ord($p_dat[$i]);

        for($i = 156, $chks += 256; $i < 512; $i++)
            $chks += ord($p_dat[$i]);

        $headers = @unpack("a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1typeflag/a100link/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor", $p_dat);
        if(!$headers) return false;

        $return['checksum'] = OctDec(trim($headers['checksum']));
        if($return['checksum'] != $chks) return false;

        $return['filename'] = trim($headers['filename']);
        $return['mode']     = OctDec(trim($headers['mode']));
        $return['uid']      = OctDec(trim($headers['uid']));
        $return['gid']      = OctDec(trim($headers['gid']));
        $return['size']     = OctDec(trim($headers['size']));
        $return['mtime']    = OctDec(trim($headers['mtime']));
        $return['typeflag'] = $headers['typeflag'];
        $return['link']     = trim($headers['link']);
        $return['uname']    = trim($headers['uname']);
        $return['gname']    = trim($headers['gname']);

        return $return;
    }

    /**
     *  Builds a normalized file list
     *
     * @todo remove string support, use saner format
     *
     * @param $p_filelist
     * @return array|bool
     */
    function _fetchFilelist($p_filelist) {
        if(!$p_filelist || (is_array($p_filelist) && !@count($p_filelist))) return false;

        if(is_string($p_filelist)) {
            $p_filelist = explode('|', $p_filelist);
            if(!is_array($p_filelist)) $p_filelist = Array($p_filelist);
        }

        return $p_filelist;
    }

    /**
     * Adds files given as file list
     *
     * @param array  $p_fl
     * @param string $p_addir
     * @param string $p_remdir
     * @return bool
     */
    function _addFileList($p_fl, $p_addir, $p_remdir) {
        foreach($p_fl as $file) {
            if(($file == $this->_nomf && $this->_nomf !== TarLib::ARCHIVE_DYNAMIC) || !$file || (!is_array($file) && !file_exists($file)))
                continue;

            if(!$this->_addFile($file, $p_addir, $p_remdir))
                continue;

            if(@is_dir($file)) {
                $d = @opendir($file);

                if(!$d) continue;
                readdir($d);
                readdir($d);

                while($f = readdir($d)) {
                    if($file != ".") $tmplist[0] = "$file/$f";
                    else $tmplist[0] = $d;

                    $this->_addFileList($tmplist, $p_addir, $p_remdir);
                }

                closedir($d);
                unset($tmplist, $f);
            }
        }
        return true;
    }

    /**
     * Adds a single file
     *
     * @param array|string $p_fn
     * @param string       $p_addir
     * @param string       $p_remdir
     * @return bool
     */
    function _addFile($p_fn, $p_addir = '', $p_remdir = '') {
        $data = false;
        if(is_array($p_fn)) list($p_fn, $data) = $p_fn;
        $sname = $p_fn;

        if($p_remdir) {
            if(substr($p_remdir, -1) != '/') $p_remdir .= "/";

            if(substr($sname, 0, strlen($p_remdir)) == $p_remdir)
                $sname = substr($sname, strlen($p_remdir));
        }

        if($p_addir) $sname = $p_addir.(substr($p_addir, -1) == '/' ? '' : "/").$sname;

        // FIXME ustar should support up 256 chars
        if(strlen($sname) > 99) return false;

        if(@is_dir($p_fn)) {
            if(!$this->_writeFileHeader($p_fn, $sname)) return false;
        } else {
            if(!$data) {
                $fp = fopen($p_fn, 'rb');
                if(!$fp) return false;
            }

            if(!$this->_writeFileHeader($p_fn, $sname, ($data ? strlen($data) : false))) return false;

            if(!$data) {
                while(!feof($fp)) {
                    $packed = pack("a512", fread($fp, 512));
                    $this->_write($packed);
                }
                fclose($fp);
            } else {
                $len = strlen($data);
                for($s = 0; $s < $len; $s += 512) {
                    $this->_write(pack("a512", substr($data, $s, 512)));
                }
            }
        }

        return true;
    }

    /**
     * Write the header for a file in the TAR archive
     *
     * @param string $p_file
     * @param string $p_sname
     * @param bool   $p_data
     * @return bool
     */
    function _writeFileHeader($p_file, $p_sname, $p_data = false) {
        if(!$p_data) {
            if(!$p_sname) $p_sname = $p_file;
            $p_sname = $this->_pathTrans($p_sname);

            $h_info = stat($p_file);
            $h[0]   = sprintf("%6s ", DecOct($h_info[4]));
            $h[]    = sprintf("%6s ", DecOct($h_info[5]));
            $h[]    = sprintf("%6s ", DecOct(fileperms($p_file)));
            clearstatcache();
            $h[] = sprintf("%11s ", DecOct(filesize($p_file)));
            $h[] = sprintf("%11s", DecOct(filemtime($p_file)));

            $dir = @is_dir($p_file) ? '5' : '';
        } else {
            $dir    = '';
            $p_data = sprintf("%11s ", DecOct($p_data));
            $time   = sprintf("%11s ", DecOct(time()));
            $h      = Array("     0 ", "     0 ", " 40777 ", $p_data, $time);
        }

        $data_first = pack("a100a8a8a8a12A12", $p_sname, $h[2], $h[0], $h[1], $h[3], $h[4]);
        $data_last  = pack("a1a100a6a2a32a32a8a8a155a12", $dir, '', '', '', '', '', '', '', '', "");

        for($i = 0, $chks = 0; $i < 148; $i++)
            $chks += ord($data_first[$i]);

        for($i = 156, $chks += 256, $j = 0; $i < 512; $i++, $j++)
            $chks += ord($data_last[$j]);

        $this->_write($data_first);

        $chks = pack("a8", sprintf("%6s ", DecOct($chks)));
        $this->_write($chks.$data_last);

        return true;
    }

    /**
     * Append the given files to the already open archive
     *
     * @param array  $p_filelist
     * @param string $p_addir
     * @param string $p_remdir
     * @return bool|int
     */
    function _append($p_filelist, $p_addir = "", $p_remdir = "") {
        if(!$this->_fp) if(!$this->_OpenWrite('a')) return -6;

        if($this->_nomf === TarLib::ARCHIVE_DYNAMIC) {
            $this->_memdat = substr($this->_memdat, 0, -512 * 2); // remove footer
        } else {
            clearstatcache();
            $s = filesize($this->_nomf);

            $this->_seek($s - (512 * 2)); // remove footer
        }

        $ok = $this->_addFileList($p_filelist, $p_addir, $p_remdir);
        $this->_writeFooter();

        return $ok;
    }

    /**
     * Cleans up a path and removes relative parts
     *
     * @param string $p_dir
     * @return string
     */
    function _pathTrans($p_dir) {
        $r = '';
        if($p_dir) {
            $subf = explode("/", $p_dir);

            for($i = count($subf) - 1; $i >= 0; $i--) {
                if($subf[$i] == ".") {
                    # do nothing
                } elseif($subf[$i] == "..") {
                    $i--;
                } elseif(!$subf[$i] && $i != count($subf) - 1 && $i) {
                    # do nothing
                } else {
                    $r = $subf[$i].($i != (count($subf) - 1) ? "/".$r : "");
                }
            }
        }
        return $r;
    }

    /**
     * Add the closing footer to the archive
     *
     * Physically, an archive consists of a series of file entries terminated by an end-of-archive entry, which
     * consists of two 512 blocks of zero bytes
     *
     * @link http://www.gnu.org/software/tar/manual/html_chapter/tar_8.html#SEC134
     */
    function _writeFooter() {
        $this->_write(pack("a512", ""));
        $this->_write(pack("a512", ""));
    }

    /**
     * @param     $p_to
     * @param     $p_files
     * @param     $p_remdir
     * @param int $p_mode
     * @return array|bool|int|string
     */
    function _extractList($p_to, $p_files, $p_remdir, $p_mode = 0755) {
        if(!$p_to || ($p_to[0] != "/" && substr($p_to, 0, 3) != "../" && substr($p_to, 1, 3) != ":\\" && substr($p_to, 1, 2) != ":/")) /*" // <- PHP Coder bug */
            $p_to = "./$p_to";

        if($p_remdir && substr($p_remdir, -1) != '/') $p_remdir .= '/';
        $p_remdirs = strlen($p_remdir);
        while($dat = $this->_read(512)) {
            $headers = $this->_readHeader($dat);
            if(!$headers['filename']) continue;

            if($p_files == -1 || $p_files[0] == -1) {
                $extract = true;
            } else {
                $extract = false;

                foreach($p_files as $f) {
                    if(substr($f, -1) == "/") {
                        if((strlen($headers['filename']) > strlen($f)) && (substr($headers['filename'], 0, strlen($f)) == $f)) {
                            $extract = true;
                            break;
                        }
                    } elseif($f == $headers['filename']) {
                        $extract = true;
                        break;
                    }
                }
            }

            if($extract) {
                $det[] = $headers;
                if($p_remdir && substr($headers['filename'], 0, $p_remdirs) == $p_remdir)
                    $headers['filename'] = substr($headers['filename'], $p_remdirs);

                if($headers['filename'].'/' == $p_remdir && $headers['typeflag'] == '5') continue;

                if($p_to != "./" && $p_to != "/") {
                    while($p_to{-1} == "/") $p_to = substr($p_to, 0, -1);

                    if($headers['filename']{0} == "/")
                        $headers['filename'] = $p_to.$headers['filename'];
                    else
                        $headers['filename'] = $p_to."/".$headers['filename'];
                }

                $ok = $this->_dirApp($headers['typeflag'] == "5" ? $headers['filename'] : dirname($headers['filename']));
                if($ok < 0) return $ok;

                if(!$headers['typeflag']) {
                    if(!$fp = @fopen($headers['filename'], "wb")) return -6;
                    $n = floor($headers['size'] / 512);

                    for($i = 0; $i < $n; $i++) {
                        fwrite($fp, $this->_read(512), 512);
                    }
                    if(($headers['size'] % 512) != 0) fwrite($fp, $this->_read(512), $headers['size'] % 512);

                    fclose($fp);
                    touch($headers['filename'], $headers['mtime']);
                    chmod($headers['filename'], $p_mode);
                } else {
                    $this->_seek(ceil($headers['size'] / 512) * 512, 1);
                }
            } else $this->_seek(ceil($headers['size'] / 512) * 512, 1);
        }
        return $det;
    }

    /**
     * Create a directory hierarchy in filesystem
     *
     * @param string $d
     * @return bool
     */
    function _dirApp($d) {
        //  map to dokuwiki function (its more robust)
        return io_mkdir_p($d);
    }

}

