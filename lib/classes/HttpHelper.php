<?php

class HttpHelper {
    const OK_PARTIAL_CONTENT = '206 Partial Content';
    const ERR_NOT_AUTHORIZED = '401 Not Authorized';
    const ERR_BAD_REQUEST = '400 Bad Request';
    const ERR_NOT_FOUND = '404 Not Found';
    const ERR_NOT_ALLOWED = '405 Method Not Allowed';
    const ERR_BAD_RANGE = '416 Requested Range Not Satisfiable';
    const ERR_SERVER_ERROR = '500 Internal Server Error';
    const ERR_NOT_IMPLEMENTED = '501 Not Implemented';
    const ERR_SERVICE_UNAVAILABLE = '503 Service Temporarily Unavailable';
    private static $mimeTypes = null;
    const MAX_STREAM_CHUNK_SIZE = 1024 * 1024;

    /**
     * Returns the base address from the request url.
     * This address takes into account that the caller may be behind a proxy server
     * E.g. The caller sent the request to "https://ws/ServerWSDL.php"
     * - return value: http://ws
     * E.g. The caller sent the request to "https://api.linkcareapp.com/ServerWSDL.php"
     * - return value: https://api.linkcareapp.com/
     *
     * @return string
     */
    static public function requestUrlBase() {
        return self::getServerProtocol() . $_SERVER['HTTP_HOST'] . "/";
    }

    /**
     * Returns the complete path of the request URL without the query part (parameters)
     */
    static public function requestUrlPath() {
        $fullUrl = self::composeUrl(self::requestUrlBase(), $_SERVER['REQUEST_URI']);
        return self::urlPath($fullUrl);
    }

    /**
     * Returns the complete path of an URL without the query part (parameters)
     */
    static public function urlPath($url) {
        if (!$url) {
            return $url;
        }
        return explode('?', $url)[0];
    }

    /**
     * Returns the base of an URL without the path and query parts
     */
    static public function urlBase($url) {
        if ($url) {
            $regexp = '~^(.+?//[^/]+).*$~';
            $matches = null;
            if (preg_match($regexp, $url, $matches)) {
                return $matches[1];
            }
        }
        return $url;
    }

    /**
     * Returns true if the url provided does not include the protocol and domain parts (e.g.
     * http://domain, ftp://domain)
     */
    static public function isRelativeUrl($url) {
        return !preg_match('~^\w+://~', strtolower($url));
    }

    /**
     * Compose a complete URL from the domain name (including the protocol part) and the relative Url
     *
     * @param string $domainName
     * @param string $relativeUrl
     * @return string
     */
    static public function composeUrl($domainName, $relativeUrl) {
        $domainName = trim($domainName, '/');
        $relativeUrl = trim($relativeUrl, '/');
        return $domainName . '/' . $relativeUrl;
    }

    /**
     * Adds a parameter to the query string of an URL (only if a non null value is provided)
     *
     * @param string $url
     * @param string $paramName
     * @param string $paramValue
     * @return string
     */
    static public function urlAddParam($url, $paramName, $paramValue) {
        if ($paramValue === null || $paramValue === '') {
            return $url;
        }
        $chars = str_split($url);
        $lastChar = end($chars);
        if (strpos($url, '?') === false) {
            $url .= '?';
        } else if (($lastChar != '?') && ($lastChar != '&')) {
            $url .= '&';
        }
        $url .= $paramName . '=' . urlencode($paramValue);
        return $url;
    }

    static private function getServerProtocol($local = false) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && !$local) {
            /*
             * The server is under a load balancer that forwarded the communication, so we must use the protocol that arrived to the load balancer
             */
            $protocol = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) . "://";
        } else {
            $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        }

        return $protocol;
    }

    /**
     * Processes the HTTP headers to prepare a HTML5 streaming
     * Returns an associative array with two values (calculated using the HTTP_RANGE headers):
     * - start: the position in the resource where the stream should start
     * - length: the total number of bytes requested
     *
     * @param string $contentType : MIME content type (e.g. video/mp4)
     * @param int $size : total size of the resource to stream
     * @return int[]
     */
    static function setErrorHeader($error) {
        header($_SERVER["SERVER_PROTOCOL"] . ' ' . $error);
    }

    static public function prepareStream($contentType = 'video/mp4', $size) {

        // Prepare HTTP headers
        ob_get_clean();
        header("Content-Type: $contentType");
        header("Cache-Control: max-age=2592000, public");

        $expiration = date('D, d M Y H:i:s', strtotime(todayUTC() . ' + 30 days'));
        header("Expires: $expiration GMT");
        header("Last-Modified: " . todayUTC('D, d M Y H:i:s') . ' GMT');
        $start = 0;
        $end = $size - 1;
        $length = $size;
        header("Accept-Ranges: bytes");

        if (isset($_SERVER['HTTP_RANGE'])) {
            $adjustedStart = 0;
            $adjustedEnd = $end;

            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') !== false) {
                HttpHelper::setErrorHeader(self::ERR_BAD_RANGE);
                header("Content-Range: bytes $start-$end/$size");
                exit();
            }
            if (startsWith('-', $range)) {
                $adjustedStart = $size - substr($range, 1);
            } else {
                $range = explode('-', $range);
                $adjustedStart = $range[0];

                $adjustedEnd = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $adjustedEnd;
            }
            $adjustedEnd = ($adjustedEnd > $end) ? $end : $adjustedEnd;
            if ($adjustedStart > $adjustedEnd || $adjustedStart > $size - 1 || $adjustedEnd >= $size) {
                HttpHelper::setErrorHeader(self::ERR_BAD_RANGE);
                header("Content-Range: bytes $start-$end/$size");
                exit();
            }
            $start = $adjustedStart;
            $end = $adjustedEnd;
            $length = $end - $start + 1;
            if ($length > self::MAX_STREAM_CHUNK_SIZE) {
                $length = self::MAX_STREAM_CHUNK_SIZE;
                $end = $start + $length - 1;
            }

            HttpHelper::setErrorHeader(self::OK_PARTIAL_CONTENT);
            header("Content-Length: " . $length);
            header("Content-Range: bytes $start-$end/$size");
        } else {
            header("Content-Length: " . $size);
        }

        return ['start' => $start, 'length' => $length];
    }

    /**
     * Normalizes a mime type.
     * If the string passed has a format like x/y (e.g. audio/mp4), it will not be modified
     * Otherwise, a file extension is expected, and the corresponding MIME type will be returned.
     * If the file extension provided is not known, then the mime type 'application/octet-stream' will be returned
     *
     * @param string $mimeType
     * @return string
     */
    static function normalizeMimeType($mimeType) {
        if (!self::$mimeTypes) {
            self::initMimeTypes();
        }

        $mimeType = strtolower($mimeType);
        if (strpos($mimeType, "/") !== false) {
            return $mimeType;
        }

        if (array_key_exists($mimeType, self::$mimeTypes)) {
            $mimeType = self::$mimeTypes[$mimeType];
        } else {
            $mimeType = 'application/octet-stream';
        }
        return $mimeType;
    }

    /**
     * Inits an associative array to store the relation between file extensions and their corresponding mime type
     */
    private function initMimeTypes() {
        self::$mimeTypes['evy'] = 'application/envoy'; // Corel Envoy
        self::$mimeTypes['fif'] = 'application/fractals'; // fractal image file
        self::$mimeTypes['spl'] = 'application/futuresplash'; // Windows print spool file
        self::$mimeTypes['hta'] = 'application/hta'; // HTML application
        self::$mimeTypes['acx'] = 'application/internet-property-stream'; // Atari ST Program
        self::$mimeTypes['hqx'] = 'application/mac-binhex40'; // BinHex encoded file
        self::$mimeTypes['doc'] = 'application/msword'; // Word document
        self::$mimeTypes['docx'] = 'application/msword'; // Word document
        self::$mimeTypes['dot'] = 'application/msword'; // Word document template
        self::$mimeTypes['dotx'] = 'application/msword'; // Word document template
        self::$mimeTypes['*'] = 'application/octet-stream'; // Binary file
        self::$mimeTypes['bin'] = 'application/octet-stream'; // binary disk image
        self::$mimeTypes['class'] = 'application/octet-stream'; // Java class file
        self::$mimeTypes['dms'] = 'application/octet-stream'; // Disk Masher image
        self::$mimeTypes['exe'] = 'application/octet-stream'; // executable file
        self::$mimeTypes['lha'] = 'application/octet-stream'; // LHARC compressed archive
        self::$mimeTypes['lzh'] = 'application/octet-stream'; // LZH compressed file
        self::$mimeTypes['oda'] = 'application/oda'; // CALS raster image
        self::$mimeTypes['axs'] = 'application/olescript'; // ActiveX script
        self::$mimeTypes['pdf'] = 'application/pdf'; // Acrobat file
        self::$mimeTypes['prf'] = 'application/pics-rules'; // Outlook profile file
        self::$mimeTypes['p10'] = 'application/pkcs10'; // certificate request file
        self::$mimeTypes['crl'] = 'application/pkix-crl'; // certificate revocation list file
        self::$mimeTypes['ai'] = 'application/postscript'; // Adobe Illustrator file
        self::$mimeTypes['eps'] = 'application/postscript'; // postscript file
        self::$mimeTypes['ps'] = 'application/postscript'; // postscript file
        self::$mimeTypes['rtf'] = 'application/rtf'; // rich text format file
        self::$mimeTypes['setpay'] = 'application/set-payment-initiation'; // set payment initiation
        self::$mimeTypes['setreg'] = 'application/set-registration-initiation'; // set registration initiation
        self::$mimeTypes['xla'] = 'application/vnd.ms-excel'; // Excel Add-in file
        self::$mimeTypes['xlc'] = 'application/vnd.ms-excel'; // Excel chart
        self::$mimeTypes['xlm'] = 'application/vnd.ms-excel'; // Excel macro
        self::$mimeTypes['xls'] = 'application/vnd.ms-excel'; // Excel spreadsheet
        self::$mimeTypes['xlsx'] = 'application/vnd.ms-excel'; // Excel spreadsheet
        self::$mimeTypes['xlt'] = 'application/vnd.ms-excel'; // Excel template
        self::$mimeTypes['xltx'] = 'application/vnd.ms-excel'; // Excel template
        self::$mimeTypes['xlw'] = 'application/vnd.ms-excel'; // Excel worspace
        self::$mimeTypes['msg'] = 'application/vnd.ms-outlook'; // Outlook mail message
        self::$mimeTypes['sst'] = 'application/vnd.ms-pkicertstore'; // serialized certificate store file
        self::$mimeTypes['cat'] = 'application/vnd.ms-pkiseccat'; // Windows catalog file
        self::$mimeTypes['stl'] = 'application/vnd.ms-pkistl'; // stereolithography file
        self::$mimeTypes['pot'] = 'application/vnd.ms-powerpoint'; // PowerPoint template
        self::$mimeTypes['pps'] = 'application/vnd.ms-powerpoint'; // PowerPoint slide show
        self::$mimeTypes['ppsx'] = 'application/vnd.ms-powerpoint'; // PowerPoint slide show
        self::$mimeTypes['ppt'] = 'application/vnd.ms-powerpoint'; // PowerPoint presentation
        self::$mimeTypes['pptx'] = 'application/vnd.ms-powerpoint'; // PowerPoint presentation
        self::$mimeTypes['mpp'] = 'application/vnd.ms-project'; // Microsoft Project file
        self::$mimeTypes['wcm'] = 'application/vnd.ms-works'; // WordPerfect macro
        self::$mimeTypes['wdb'] = 'application/vnd.ms-works'; // Microsoft Works database
        self::$mimeTypes['wks'] = 'application/vnd.ms-works'; // Microsoft Works spreadsheet
        self::$mimeTypes['wps'] = 'application/vnd.ms-works'; // Microsoft Works word processsor document
        self::$mimeTypes['hlp'] = 'application/winhlp'; // Windows help file
        self::$mimeTypes['bcpio'] = 'application/x-bcpio'; // binary CPIO archive
        self::$mimeTypes['cdf'] = 'application/x-cdf'; // computable document format file
        self::$mimeTypes['z'] = 'application/x-compress'; // Unix compressed file
        self::$mimeTypes['tgz'] = 'application/x-compressed'; // gzipped tar file
        self::$mimeTypes['cpio'] = 'application/x-cpio'; // Unix CPIO archive
        self::$mimeTypes['csh'] = 'application/x-csh'; // Photoshop custom shapes file
        self::$mimeTypes['dcr'] = 'application/x-director'; // Kodak RAW image file
        self::$mimeTypes['dir'] = 'application/x-director'; // Adobe Director movie
        self::$mimeTypes['dxr'] = 'application/x-director'; // Macromedia Director movie
        self::$mimeTypes['dvi'] = 'application/x-dvi'; // device independent format file
        self::$mimeTypes['gtar'] = 'application/x-gtar'; // Gnu tar archive
        self::$mimeTypes['gz'] = 'application/x-gzip'; // Gnu zipped archive
        self::$mimeTypes['hdf'] = 'application/x-hdf'; // hierarchical data format file
        self::$mimeTypes['ins'] = 'application/x-internet-signup'; // internet settings file
        self::$mimeTypes['isp'] = 'application/x-internet-signup'; // IIS internet service provider settings
        self::$mimeTypes['iii'] = 'application/x-iphone'; // ARC+ architectural file
        self::$mimeTypes['js'] = 'application/x-javascript'; // JavaScript file
        self::$mimeTypes['latex'] = 'application/x-latex'; // LaTex document
        self::$mimeTypes['mdb'] = 'application/x-msaccess'; // Microsoft Access database
        self::$mimeTypes['crd'] = 'application/x-mscardfile'; // Windows CardSpace file
        self::$mimeTypes['clp'] = 'application/x-msclip'; // CrazyTalk clip file
        self::$mimeTypes['dll'] = 'application/x-msdownload'; // dynamic link library
        self::$mimeTypes['m13'] = 'application/x-msmediaview'; // Microsoft media viewer file
        self::$mimeTypes['m14'] = 'application/x-msmediaview'; // Steuer2001 file
        self::$mimeTypes['mvb'] = 'application/x-msmediaview'; // multimedia viewer book source file
        self::$mimeTypes['wmf'] = 'application/x-msmetafile'; // Windows meta file
        self::$mimeTypes['mny'] = 'application/x-msmoney'; // Microsoft Money file
        self::$mimeTypes['pub'] = 'application/x-mspublisher'; // Microsoft Publisher file
        self::$mimeTypes['scd'] = 'application/x-msschedule'; // Turbo Tax tax schedule list
        self::$mimeTypes['trm'] = 'application/x-msterminal'; // FTR media file
        self::$mimeTypes['wri'] = 'application/x-mswrite'; // Microsoft Write file
        self::$mimeTypes['cdf'] = 'application/x-netcdf'; // computable document format file
        self::$mimeTypes['nc'] = 'application/x-netcdf'; // Mastercam numerical control file
        self::$mimeTypes['pma'] = 'application/x-perfmon'; // MSX computers archive format
        self::$mimeTypes['pmc'] = 'application/x-perfmon'; // performance monitor counter file
        self::$mimeTypes['pml'] = 'application/x-perfmon'; // process monitor log file
        self::$mimeTypes['pmr'] = 'application/x-perfmon'; // Avid persistant media record file
        self::$mimeTypes['pmw'] = 'application/x-perfmon'; // Pegasus Mail draft stored message
        self::$mimeTypes['p12'] = 'application/x-pkcs12'; // personal information exchange file
        self::$mimeTypes['pfx'] = 'application/x-pkcs12'; // PKCS #12 certificate file
        self::$mimeTypes['p7b'] = 'application/x-pkcs7-certificates'; // PKCS #7 certificate file
        self::$mimeTypes['spc'] = 'application/x-pkcs7-certificates'; // software publisher certificate file
        self::$mimeTypes['p7r'] = 'application/x-pkcs7-certreqresp'; // certificate request response file
        self::$mimeTypes['p7c'] = 'application/x-pkcs7-mime'; // PKCS #7 certificate file
        self::$mimeTypes['p7m'] = 'application/x-pkcs7-mime'; // digitally encrypted message
        self::$mimeTypes['p7s'] = 'application/x-pkcs7-signature'; // digitally signed email message
        self::$mimeTypes['sh'] = 'application/x-sh'; // Bash shell script
        self::$mimeTypes['shar'] = 'application/x-shar'; // Unix shar archive
        self::$mimeTypes['swf'] = 'application/x-shockwave-flash'; // Flash file
        self::$mimeTypes['sit'] = 'application/x-stuffit'; // Stuffit archive file
        self::$mimeTypes['sv4cpio'] = 'application/x-sv4cpio'; // system 5 release 4 CPIO file
        self::$mimeTypes['sv4crc'] = 'application/x-sv4crc'; // system 5 release 4 CPIO checksum data
        self::$mimeTypes['tar'] = 'application/x-tar'; // consolidated Unix file archive
        self::$mimeTypes['tcl'] = 'application/x-tcl'; // Tcl script
        self::$mimeTypes['tex'] = 'application/x-tex'; // LaTeX source document
        self::$mimeTypes['texi'] = 'application/x-texinfo'; // LaTeX info document
        self::$mimeTypes['texinfo'] = 'application/x-texinfo'; // LaTeX info document
        self::$mimeTypes['roff'] = 'application/x-troff'; // unformatted manual page
        self::$mimeTypes['t'] = 'application/x-troff'; // Turing source code file
        self::$mimeTypes['tr'] = 'application/x-troff'; // TomeRaider 2 ebook file
        self::$mimeTypes['man'] = 'application/x-troff-man'; // Unix manual
        self::$mimeTypes['me'] = 'application/x-troff-me'; // readme text file
        self::$mimeTypes['ms'] = 'application/x-troff-ms'; // 3ds Max script file
        self::$mimeTypes['ustar'] = 'application/x-ustar'; // uniform standard tape archive format file
        self::$mimeTypes['src'] = 'application/x-wais-source'; // source code
        self::$mimeTypes['cer'] = 'application/x-x509-ca-cert'; // internet security certificate
        self::$mimeTypes['crt'] = 'application/x-x509-ca-cert'; // security certificate
        self::$mimeTypes['der'] = 'application/x-x509-ca-cert'; // DER certificate file
        self::$mimeTypes['pko'] = 'application/ynd.ms-pkipko'; // public key security object
        self::$mimeTypes['zip'] = 'application/zip'; // zipped file
        self::$mimeTypes['au'] = 'audio/basic'; // audio file
        self::$mimeTypes['snd'] = 'audio/basic'; // sound file
        self::$mimeTypes['mid'] = 'audio/mid'; // midi file
        self::$mimeTypes['rmi'] = 'audio/mid'; // media processing server studio
        self::$mimeTypes['mp3'] = 'audio/mpeg'; // MP3 file
        self::$mimeTypes['aif'] = 'audio/x-aiff'; // audio interchange file format
        self::$mimeTypes['aifc'] = 'audio/x-aiff'; // compressed audio interchange file
        self::$mimeTypes['aiff'] = 'audio/x-aiff'; // audio interchange file format
        self::$mimeTypes['m3u'] = 'audio/x-mpegurl'; // media playlist file
        self::$mimeTypes['ra'] = 'audio/x-pn-realaudio'; // Real Audio file
        self::$mimeTypes['ram'] = 'audio/x-pn-realaudio'; // Real Audio metadata file
        self::$mimeTypes['wav'] = 'audio/x-wav'; // WAVE audio file
        self::$mimeTypes['bmp'] = 'image/bmp'; // Bitmap
        self::$mimeTypes['cod'] = 'image/cis-cod'; // compiled source code
        self::$mimeTypes['gif'] = 'image/gif'; // graphic interchange format
        self::$mimeTypes['ief'] = 'image/ief'; // image file
        self::$mimeTypes['jpe'] = 'image/jpeg'; // JPEG image
        self::$mimeTypes['jpeg'] = 'image/jpeg'; // JPEG image
        self::$mimeTypes['jpg'] = 'image/jpeg'; // JPEG image
        self::$mimeTypes['png'] = 'image/png'; // JPEG image
        self::$mimeTypes['jfif'] = 'image/pipeg'; // JPEG file interchange format
        self::$mimeTypes['svg'] = 'image/svg+xml'; // scalable vector graphic
        self::$mimeTypes['tif'] = 'image/tiff'; // TIF image
        self::$mimeTypes['tiff'] = 'image/tiff'; // TIF image
        self::$mimeTypes['ras'] = 'image/x-cmu-raster'; // Sun raster graphic
        self::$mimeTypes['cmx'] = 'image/x-cmx'; // Corel metafile exchange image file
        self::$mimeTypes['ico'] = 'image/x-icon'; // icon
        self::$mimeTypes['pnm'] = 'image/x-portable-anymap'; // portable any map image
        self::$mimeTypes['pbm'] = 'image/x-portable-bitmap'; // portable bitmap image
        self::$mimeTypes['pgm'] = 'image/x-portable-graymap'; // portable graymap image
        self::$mimeTypes['ppm'] = 'image/x-portable-pixmap'; // portable pixmap image
        self::$mimeTypes['rgb'] = 'image/x-rgb'; // RGB bitmap
        self::$mimeTypes['xbm'] = 'image/x-xbitmap'; // X11 bitmap
        self::$mimeTypes['xpm'] = 'image/x-xpixmap'; // X11 pixmap
        self::$mimeTypes['xwd'] = 'image/x-xwindowdump'; // X-Windows dump image
        self::$mimeTypes['mht'] = 'message/rfc822'; // MHTML web archive
        self::$mimeTypes['mhtml'] = 'message/rfc822'; // MIME HTML file
        self::$mimeTypes['nws'] = 'message/rfc822'; // Windows Live Mail newsgroup file
        self::$mimeTypes['css'] = 'text/css'; // Cascading Style Sheet
        self::$mimeTypes['323'] = 'text/h323'; // H.323 internet telephony file
        self::$mimeTypes['xml'] = 'text/xml'; // HTML file
        self::$mimeTypes['htm'] = 'text/html'; // HTML file
        self::$mimeTypes['html'] = 'text/html'; // HTML file
        self::$mimeTypes['stm'] = 'text/html'; // Exchange streaming media file
        self::$mimeTypes['uls'] = 'text/iuls'; // NetMeeting user location service file
        self::$mimeTypes['bas'] = 'text/plain'; // BASIC source code file
        self::$mimeTypes['c'] = 'text/plain'; // C/C++ source code file
        self::$mimeTypes['h'] = 'text/plain'; // C/C++/Objective C header file
        self::$mimeTypes['txt'] = 'text/plain'; // text file
        self::$mimeTypes['rtx'] = 'text/richtext'; // rich text file
        self::$mimeTypes['sct'] = 'text/scriptlet'; // Scitext continuous tone file
        self::$mimeTypes['tsv'] = 'text/tab-separated-values'; // tab separated values file
        self::$mimeTypes['htt'] = 'text/webviewhtml'; // hypertext template file
        self::$mimeTypes['htc'] = 'text/x-component'; // HTML component file
        self::$mimeTypes['etx'] = 'text/x-setext'; // TeX font encoding file
        self::$mimeTypes['vcf'] = 'text/x-vcard'; // vCard file
        self::$mimeTypes['mp2'] = 'video/mpeg'; // MPEG-2 audio file
        self::$mimeTypes['mpa'] = 'video/mpeg'; // MPEG-2 audio file
        self::$mimeTypes['mpe'] = 'video/mpeg'; // MPEG movie file
        self::$mimeTypes['mpeg'] = 'video/mpeg'; // MPEG movie file
        self::$mimeTypes['mpg'] = 'video/mpeg'; // MPEG movie file
        self::$mimeTypes['mpv2'] = 'video/mpeg'; // MPEG-2 video stream
        self::$mimeTypes['mp4'] = 'video/mp4'; // MPEG-4
        self::$mimeTypes['mov'] = 'video/quicktime'; // Apple QuickTime movie
        self::$mimeTypes['qt'] = 'video/quicktime'; // Apple QuickTime movie
        self::$mimeTypes['lsf'] = 'video/x-la-asf'; // Logos library system file
        self::$mimeTypes['lsx'] = 'video/x-la-asf'; // streaming media shortcut
        self::$mimeTypes['asf'] = 'video/x-ms-asf'; // advanced systems format file
        self::$mimeTypes['asr'] = 'video/x-ms-asf'; // ActionScript remote document
        self::$mimeTypes['asx'] = 'video/x-ms-asf'; // Microsoft ASF redirector file
        self::$mimeTypes['avi'] = 'video/x-msvideo'; // audio video interleave file
        self::$mimeTypes['movie'] = 'video/x-sgi-movie'; // Apple QuickTime movie
        self::$mimeTypes['3gp'] = 'video/3gpp';
        self::$mimeTypes['3gpp'] = 'video/3gpp';
        self::$mimeTypes['3g2'] = 'video/3gpp2';
        self::$mimeTypes['3gpp2'] = 'video/3gpp2';
        self::$mimeTypes['flr'] = 'x-world/x-vrml'; // Flare decompiled actionscript file
        self::$mimeTypes['vrml'] = 'x-world/x-vrml'; // VRML file
        self::$mimeTypes['wrl'] = 'x-world/x-vrml'; // VRML world
        self::$mimeTypes['wrz'] = 'x-world/x-vrml'; // compressed VRML world
        self::$mimeTypes['xaf'] = 'x-world/x-vrml'; // 3ds max XML animation file
        self::$mimeTypes['xof'] = 'x-world/x-vrml'; // Reality Lab 3D image file
    }
}
