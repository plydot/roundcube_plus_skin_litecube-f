<?php
namespace XFramework;

/**
 * Roundcube Plus Framework plugin.
 *
 * Copyright 2017, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

class Utils
{
    /**
     * Returns the current user IP. This function takes into account the config variable remote_addr_key, which can be
     * used to change the key used to retrieve the user IP from the $_SERVER variable.
     *
     * @return string
     */
    static public function getRemoteAddr()
    {
        $key = \rcmail::get_instance()->config->get("remote_addr_key");

        if (!empty($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return "";
    }

    /**
     * Converts an integer to a human-readeable file size string.
     *
     * @param int $size
     * @return string
     */
    static public function sizeToString($size)
    {
        if (!is_numeric($size)) {
            return "-";
        }

        $units = array("B", "kB", "MB", "GB", "TB", "PB");
        $index = 0;

        while ($size >= 1000) {
            $size /= 1000;
            $index++;
        }

        return round($size) . " " . $units[$index];
    }

    /**
     * Shortens a string to the specified length and appends (...). If the string is shorter than the specified length,
     * the string will be left intact.
     *
     * @param string $string
     * @param int $length
     * @return string
     */
    public static function shortenString($string, $length = 50)
    {
        $string = trim($string);

        if (strlen($string) <= $length) {
            return $string;
        }

        $string = substr($string, 0, $length);

        if ($i = strrpos($string, " ")) {
            $string = substr($string, 0, $i);
        }

        return $string . "...";
    }

    /**
     * Returns a string containing a relative path for saving files based on the passed id. This is used for limiting
     * the amount of files stored in a single directory.
     *
     * @param int $id
     * @param int $idsPerDir
     * @param int $levels
     * @return string
     */
    public static function structuredDirectory($id, $idsPerDir = 500, $levels = 2)
    {
        if ($idsPerDir <= 0) {
            $idsPerDir = 100;
        }

        if ($levels < 1 || $levels > 3) {
            $levels = 2;
        }

        $level1 = floor($id / $idsPerDir);
        $level2 = floor($level1 / 1000);
        $level3 = floor($level2 / 1000);

        return ($levels > 2 ? sprintf("%03d", $level3 % 1000) . "/" : "") .
            ($levels > 1 ? sprintf("%03d", $level2 % 1000) . "/" : "") .
            sprintf("%03d", $level1 % 1000) . "/";
    }


    /**
     * Returns a string that is sure to be a valid file name.
     *
     * @param string $string
     * @return string
     */
    public static function ensureFileName($string)
    {
        $result = preg_replace("/[\/\\\:\?\*\+\%\|\"\<\>]/i", "_", strtolower($string));
        $result = trim(preg_replace("([\_]{2,})", "_", $result), "_ \t\n\r\0\x0B");
        return $result ? $result : "unknown";
    }

    /**
     * Returns a unique file name. This function generates a random name, then checks if the file with this name already
     * exists in the specified directory. If it does, it generates a new random file name.
     *
     * @param string $path
     * @param string $ext
     * @param string $prefix
     * @return string
     */
    public static function uniqueFileName($path, $ext = false, $prefix = false)
    {
        if (strlen($ext) && $ext[0] != ".") {
            $ext = "." . $ext;
        }

        $path = self::addSlash($path);

        do {
            $fileName = uniqid($prefix, true) . $ext;
        } while (file_exists($path . $fileName));

        return $fileName;
    }

    /**
     * Extracts the extension from file name.
     *
     * @param string $fileName
     * @return string
     */
    public static function ext($fileName)
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    }

    /**
     * Creates an empty directory with write permissions. It returns true if the directory already exists and is
     * writable. Also, if umask is set, mkdir won't create the directory with 0777 permissions, for exmple, if umask
     * is 0022, the outcome will be 0777-0022 = 0755, so we reset umask before creating the directory.
     *
     * @param string $dir
     * @return boolean
     */
	public static function makeDir($dir)
	{
		if (file_exists($dir)) {
            return is_writable($dir);
        }

		$umask = umask(0);
		$result = @mkdir($dir, 0777, true);
		umask($umask);

		return $result;
	}

    /**
     * Recursively removes a directory (including all the hidden files.)
     *
     * @param string $dir
     * @param bool $followLinks Should we follow directory links?
     * @param bool $contentsOnly Removes contents only leaving the directory itself intact.
     * @return boolean
     */
    public static function removeDir($dir, $followLinks = false, $contentsOnly = false)
    {
        if (empty($dir) || !is_dir($dir)) {
            return true;
        }

        $dir = self::addSlash($dir);
        $files = array_diff(scandir($dir), array(".", ".."));

        foreach ($files as $file) {
            if (is_dir($dir . $file)) {
                self::removeDir($dir . $file, $followLinks, false);
                continue;
            }

            if (is_link($dir . $file) && $followLinks) {
                unlink(readlink($dir . $file));
            }

            unlink($dir . $file);
        }

        return $contentsOnly ? true : rmdir($dir);
    }

    /**
     * Returns the current url. Optionally it appends a path specified by the $path parameter.
     *
     * @param string $path
     * @return string|boolean
     */
	public static function getUrl($path = false, $hostOnly = false, $cut = false)
	{
        // if absolute path specified, simply return it
        if (strpos($path, "://")) {
            return $path;
        }

        // check if an overwrite url specified in the config
        // (rcmail might or might not exist, for example, during some caldav requests it doesn't)
        if (class_exists("rcmail")) {
            $rcmail = \rcmail::get_instance();
            $overwriteUrl = $rcmail->config->get("overwrite_roundcube_url");
        } else {
            $overwriteUrl = false;
        }

        $parts = parse_url(empty($overwriteUrl) ? $_SERVER['REQUEST_URI'] : $overwriteUrl);
        $urlPath = empty($parts['path']) ? "" : $parts['path'];

        if (!empty($parts['scheme'])) {
            $scheme = strtolower($parts['scheme']) == "https" ? "https" : "http";
        } else {
            $scheme = empty($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != "on" ? "http" : "https";
        }

        if (!empty($parts['host'])) {
            $host = $parts['host'];
        } else {
            $host = empty($_SERVER['SERVER_NAME']) ? false : $_SERVER['SERVER_NAME'];
        }

        if (!empty($parts['port'])) {
            $port = $parts['port'];
        } else {
            $port = empty($_SERVER['SERVER_PORT']) ? "80" : $_SERVER['SERVER_PORT'];
        }

        // if url not specified in the config, check for proxy values
        if (empty($overwriteUrl)) {
            empty($_SERVER['HTTP_X_FORWARDED_PROTO']) || ($scheme = $_SERVER['HTTP_X_FORWARDED_PROTO']);
            empty($_SERVER['HTTP_X_FORWARDED_HOST']) || ($host = $_SERVER['HTTP_X_FORWARDED_HOST']);
            empty($_SERVER['HTTP_X_FORWARDED_PORT']) || ($port = $_SERVER['HTTP_X_FORWARDED_PORT']);
        }

        // if full url specified but without the protocol, prepend http or https and return.
        // we can't just leave it as is because roundcube will prepend the current domain
        if (strpos($path, "//") === 0) {
            return $scheme . ":" . $path;
        }

        // we have to have the host
        if (empty($host)) {
            return false;
        }

        // if need host only, return it
        if ($hostOnly) {
            return $host;
        }

        // format port
        if ($port && is_numeric($port) && $port != "443" && $port != "80") {
            $port = ":" . $port;
        } else {
            $port = "";
        }

        // in cpanel $urlPath will have index.php at the end
        if (substr($urlPath, -4) == ".php") {
            $urlPath = dirname($urlPath);
        }

        // if path begins with a slash, cut it
        if (strpos($path, "/") === 0) {
            $path = substr($path, 1);
        }

        $result = self::addSlash($scheme . "://" . $host . $port . $urlPath);

        // if paths to cut were specified, find and cut the resulting url
        if ($cut) {
            if (!is_array($cut)) {
                $cut = array($cut);
            }

            foreach ($cut as $val) {
                if (($pos = strpos($result, $val)) !== false) {
                    $result = substr($result, 0, $pos);
                }
            }
        }

        return $result . $path;
	}

    /**
     * Returns true if the program runs under cPanel.
     *
     * @return bool
     */
    public static function isCpanel()
    {
        return strpos(self::getUrl(), "/cpsess") !== false;
    }

    /**
     * Removes the slash from the end of a string.
     *
     * @param string $string
     * @return string
     */
	public static function removeSlash($string)
	{
		return substr($string, -1) == '/' || substr($string, -1) == '\\' ? substr($string, 0, -1) : $string;
	}

    /**
     * Adds a slash to the end of the string.
     *
     * @param string $string
     * @return string
     */
	public static function addSlash($string)
	{
		return substr($string, -1) == '/' || substr($string, -1) == '\\' ? $string : $string . '/';
	}

    /**
     * Converts a string representation of the boolean "true" or "false" into the actual boolean value.
     *
     * @param string $value
     * @return boolean
     */
    public static function strToBool($value)
    {
        switch ($value) {
            case "true":
                return true;
            case "false":
                return false;
            default:
                return $value;
        }
    }

    /**
     * Creates a random token composed of lower case letters and numbers.
     *
     * @param int $length
     * @return string
     */
    public static function randomToken($length = 32)
    {
        $characters = "abcdefghijklmnopqrstuvwxyz1234567890";
        $charactersLength = strlen($characters);
        $result = "";

        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[mt_rand(0, $charactersLength - 1)];
        }

        return $result;
    }

    /**
     * Encodes an integer id using Roundcube's desk key and returns hex string.
     *
     * @param int $id
     * @return string
     */
    public static function encodeId($id, $rcmail = null)
    {
        if (!$rcmail) {
            $rcmail = \rcmail::get_instance();
        }

        return dechex(crc32($rcmail->config->get("des_key")) + $id);
    }

    /**
     * Decodes an id encoded using encodeId()
     *
     * @param string $encodedId
     * @return int
     */
    public static function decodeId($encodedId, $rcmail = null)
    {
        if (!$rcmail) {
            $rcmail = \rcmail::get_instance();
        }

        return hexdec($encodedId) - crc32($rcmail->config->get("des_key"));
    }

    /**
     * Creates a string that contains encrypted information about an action and its associated data. This function can
     * be used to create strings in the url that are masked from the users.
     *
     * @param string $action
     * @param string $data
     * @return string
     */
    public static function encodeUrlAction($action, $data, $rcmail = null)
    {
        if (!$rcmail) {
            $rcmail = \rcmail::get_instance();
        }

        $array = array("action" => $action, "data" => $data);

        return rtrim(strtr(base64_encode($rcmail->encrypt(json_encode($array), "des_key", false)), "+/", "-_"), "=");
    }

    /**
     * Decodes a string encoded with encodeUrlAction()
     *
     * @param string $encoded
     * @param string $data
     * @return string|boolean
     */
    public static function decodeUrlAction($encoded, &$data, $rcmail = null)
    {
        if (!$rcmail) {
            $rcmail = \rcmail::get_instance();
        }

        $array = json_decode($rcmail->decrypt(
            base64_decode(str_pad(strtr($encoded, "-_", "+/"), strlen($encoded) % 4, "=", STR_PAD_RIGHT)),
            "des_key",
            false
        ), true);

        if (is_array($array) && array_key_exists("action", $array) && array_key_exists("data", $array)) {
            $data = $array['data'];
            return $array['action'];
        }

        return false;
    }

    /**
     * Packs data into a compressed, encoded format.
     *
     * @param $data
     * @return bool|string
     */
    public static function pack(array $data)
    {
        $l = $data['lc'];
        $data = json_encode($data);
        $iv = openssl_random_pseudo_bytes(16, $ret);
        $akey = "4938" . openssl_random_pseudo_bytes(32, $ret);
        $header = "687474703a2f2f616e616c79746963732e726f756e6463756265706c75732e636f6d3f713d";
        $pkey = self::decodeBinary("LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0KTUlJQklqQU5CZ2txaGtpRzl3MEJBUUVGQUFPQ0FROEFNS".
            "UlCQ2dLQ0FRRUF5YkQ3enovUHlPdy9yUEdQK2o3MQpkWUhPUDJuRjhKRUYycGtZTXZWYVdaam1XWUR1ZCsrYU1JMXkvTEJZRXZMaVJte".
            "jh4NFBoTkZaMW9tenBrU0sxCjBUdWp2L2lTcDY3V3lDcjR2d2Y2eWVLMTdrbm5LOVovQXBtcE5CM09kQ3RRVFVEck80aDNWZTArMUVYQ".
            "TR4ZkQKQjBrVnAyNVJQYmw2ZHdaMytjQlh4OHZ0cDhwNUlmTEZ0ODZvVHEydzZBeUQvUGU5Y1pkcENpcUU2K0FwU0tLWgpRKzFQNXdod".
            "0hkcnYxNlJhVWtqR0NpNjkrNkpVYzdDajQwNDJjNng4ZnFTY0xpcDN2VmI0ZmRpMUUyVXZOZVVSCnhZdklLbml5a1lnMWczMitRdjJ1c".
            "Dc4THlVdmlleVh2TlJYcnZXdS9obXlQeFpkMjVYYUVLK1V4ZFNLNy9hNWYKUlFJREFRQUIKLS0tLS1FTkQgUFVCTElDIEtFWS0tLS0t");

        if (function_exists("gzcompress") && strlen($data) > 64) {
            $data = gzcompress($data, 9);
            $comp = "1";
        } else {
            $comp = "0";
        }

        if (!openssl_public_encrypt($akey, $kb, $pkey) ||
            !($ekey = self::encodeBinary($kb)) ||
            !($db = openssl_encrypt("5791" . $data, "AES-256-CBC", $akey, 1, $iv)) ||
            !($edb = self::encodeBinary($db))
        ) {
            return false;
        }

        return pack("H*", $header) . "6472" . $comp . bin2hex(pack("S", strlen($ekey))) .
            bin2hex(pack("S", strlen($edb))) . bin2hex($iv) . $ekey . $edb . "&l=$l";
    }

    /**
     * Encodes binary data using base64.
     *
     * @param $data
     * @return string
     */
    public static function encodeBinary($data)
    {
        return urlencode(rtrim(strtr(base64_encode($data), "+/", "-_"), "="));
    }

    /**
     * Decodes base64-encoded binary string.
     *
     * @param $data
     * @return bool|string
     */
    public static function decodeBinary($data)
    {
        return base64_decode(str_pad(strtr($data, "-_", "+/"), strlen($data) % 4, "=", STR_PAD_RIGHT));
    }

    /**
     * Loads the specified config file and returns the array of config options.
     *
     * @param string $configFile
     * @return array
     */
    public static function loadConfigFile($configFile)
    {
        $config = [];

        if (file_exists($configFile)) {
            include($configFile);
        }

        return $config;
    }

    /**
     * Logs a message in the Roundcube error log or system error file.
     * 
     * @param $error
     */
    public static function logError($error)
    {
        $bt = debug_backtrace(null, 2);
        $info = "XF-" . XFRAMEWORK_VERSION . " ";

        isset($bt[1]['class']) && ($info .= $bt[1]['class'] . "::");
        isset($bt[1]['function']) && ($info .= $bt[1]['function']);
        isset($bt[1]['line']) && ($info .= " " . $bt[1]['line']);

        $error = "Roundcube Plus error: $error [" . trim($info) . "]";

        if (class_exists("\\rcube") && @\rcube::write_log('errors', $error)) {
            return true;
        }

        return error_log($error);
    }

    /**
     * Logs a message in a custom log file. This method doesn't depend on the presence of the RC log methods.
     *
     * @param string $text
     * @param string $file
     * @return bool|int
     */
    public static function xlog($text, $file = "xlog")
    {
        return file_put_contents(
            rtrim(RCUBE_INSTALL_PATH, "/") . "/logs/$file",
            date("[Y-m-d H:i:s] ") . $text . "\n",
            FILE_APPEND
        );
    }

    /**
     * Removes parameters from a url and returns the new url.
     *
     * @param array|string $variables
     * @param string|bool $url If not specified, the current url will be used.
     * @return string
     */
    public static function removeVarsFromUrl($variables, $url = false)
    {
        $url || $url = self::getUrl();
        $queryStart = strpos($url, "?");

        if (!$variables || !$queryStart) {
            return $url;
        }

        if (!is_array($variables)) {
            $variables = array($variables);
        }

        parse_str(parse_url($url, PHP_URL_QUERY), $array);

        foreach ($variables as $val) {
            unset($array[$val]);
        }

        $query = http_build_query($array);

        return substr($url, 0, $queryStart) . ($query ? "?" . $query : "");
    }

    /**
     * Adds parameters to a url and returns the new url.
     *
     * @param array $variables
     * @param string|bool $url If not specified, the current url will be used.
     * @return string
     */
    public static function addVarsToUrl(array $variables, $url = false)
    {
        $url || $url = self::getUrl();

        if (empty($variables)) {
            return $url;
        }

        parse_str(parse_url($url, PHP_URL_QUERY), $array);

        foreach ($variables as $key => $val) {
            $array[$key] = $val;
        }

        if (($i = strpos($url, "?"))) {
            $url = substr($url, 0, $i);
        }

        return $url . "?" . http_build_query($array);
    }

    /**
     * Moves the uploaded image file, checking and re-saving it to avoid any potential security risks.
     *
     * @param array $uploadInfo
     * @param string $targetFile
     * @param bool|int $maxSize
     * @param bool|string $error
     * @return bool
     */
    public static function saveUploadedImage(array $uploadInfo, $targetFile, $maxSize = false, &$error = false)
    {
        $allowedExtensions = array("png", "jpg", "jpeg", "gif");
        $filePath = $uploadInfo['tmp_name'];
        $fileName = self::ensureFileName($uploadInfo['name']);
        $fileSize = $uploadInfo['size'];
        $image = null;

        try {
            // check if the file name is set
            if (empty($fileName) || $fileName == "unknown") {
                throw new \Exception("Invalid file name. (44350)");
            }

            // check if file too large
            if ($maxSize && $fileSize > $maxSize) {
                throw new \Exception("#filesizeerror");
            }

            // check if there is an upload error
            if (!empty($uploadInfo['error'])) {
                throw new \Exception("The file has not been uploaded properly. (44351)");
            }

            // check if the uploaded file exists
            if (empty($filePath) || empty($fileSize) || !file_exists($filePath)) {
                throw new \Exception("The file has not been uploaded properly. (44352)");
            }

            // check if the file is an uploaded file
            if (!is_uploaded_file($filePath)) {
                throw new \Exception("The file has not been uploaded properly. (44353)");
            }

            // check the uploaded file extension
            $pathInfo = pathinfo($fileName);

            if (!in_array(strtolower($pathInfo['extension']), $allowedExtensions)) {
                throw new \Exception("#invalid_image_extension");
            }

            // check if dstFile has an allowed extension (allow only no extension, png, jpg and gif)
            $pathInfo = pathinfo($targetFile);

            if (!empty($pathInfo['extension']) && !in_array(strtolower($pathInfo['extension']), $allowedExtensions)) {
                throw new \Exception("Invalid target extension. (44354)");
            }

            // check if target dir exists and try creating it if it doesn't
            if (!self::makeDir(dirname($targetFile))) {
                throw new \Exception("Cannot create target directory or the directory is not writable. (44355)");
            }

            // delete the target file is if exists
            if (file_exists($targetFile) && !@unlink($targetFile)) {
                throw new \Exception("Cannot overwrite the target file. (44356).");
            }

            // get the image mime type
            $info = finfo_open(FILEINFO_MIME_TYPE);
            $type = finfo_file($info, $filePath);

            // open the image
            switch ($type) {
                case "image/jpeg":
                    $image = imagecreatefromjpeg($filePath);
                    break;

                case "image/png":
                    $image = imagecreatefrompng($filePath);
                    break;

                case "image/gif":
                    $image = imagecreatefromgif($filePath);
                    break;

                default:
                    $image = false;
            }

            if (!$image) {
                throw new \Exception("#invalid_image_format");
            }

            // save the image to the target file
            switch ($type) {
                case "image/jpeg":
                    $result = imagejpeg($image, $targetFile, 75);
                    break;

                case "image/png":
                    imagesavealpha($image , true); // preserve png transparency
                    $result = imagepng($image, $targetFile, 9);
                    break;

                case "image/gif":
                    $result = imagegif($image, $targetFile);
                    break;

                default:
                    $result = false;
            }

            // verify if the image was successfully saved
            if (!$result || !file_exists($targetFile)) {
                throw new \Exception("Cannot save the uploaded image (44356).");
            }

            // verify the target file mime type
            $info = finfo_open(FILEINFO_MIME_TYPE);
            if (finfo_file($info, $targetFile) != $type) {
                throw new \Exception("Cannot save the uploaded image (44357).");
            }

            // remove the source file and image resource
            @unlink($filePath);
            imagedestroy($image);
            return true;

        } catch (\Exception $e) {
            $image && imagedestroy($image);
            file_exists($filePath) && @unlink($filePath);
            $error = $e->getMessage();
            return false;
        }
    }
}