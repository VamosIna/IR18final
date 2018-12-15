<?php


class IMDB
{
    /**
     * Set this to true if you run into problems.
     */
    const IMDB_DEBUG = false;

    /**
     * Set the preferred language for the User Agent.
     */
    const IMDB_LANG = 'en-US,en;q=0.9';

    /**
     * Define the timeout for cURL requests.
     */
    const IMDB_TIMEOUT = 15;
    const IMDB_ASPECT_RATIO  = '~<td[^>]*>Aspect\s*Ratio</td>\s*<td>(.+)</td>~Uis';
    const IMDB_AWARDS        = '~<div\s*class="titlereference-overview-section">\s*Awards:(.+)</div>~Uis';
    const IMDB_CHAR          = '~<td class="character">(?:\s+)<div>(.*)(?:\s+)(?: /| \(.*\)|<\/div>)~Ui';
    const IMDB_COLOR         = '~<a href="\/search\/title\?colors=(?:.*)">(.*)<\/a>~Ui';

    const IMDB_COUNTRY       = '~<a href="/country/(\w+)">(.*)</a>~Ui';
    const IMDB_CREATOR       = '~<div[^>]*>\s*(?:Creator|Creators)\s*:\s*<ul[^>]*>(.+)</ul>~Uxsi';
    const IMDB_DIRECTOR      = '~<div[^>]*>\s*(?:Director|Directors)\s*:\s*<ul[^>]*>(.+)</ul>~Uxsi';
    const IMDB_GENRE         = '~href="/genre/([a-zA-Z_-]*)/?">([a-zA-Z_ -]*)</a>~Ui';
    const IMDB_ID            = '~((?:tt\d{6,})|(?:itle\?\d{6,}))~';
    const IMDB_NAME          = '~href="/name/(.+)/?(?:\?[^"]*)?"[^>]*>(.+)</a>~Ui';
    const IMDB_DESCRIPTION   = '~<section class="titlereference-section-overview">\s+<div>(.*)</div>\s+<hr>~Ui';
    const IMDB_NOT_FOUND     = '~<h1 class="findHeader">No results found for ~Ui';

    const IMDB_RATING        = '~class="ipl-rating-star__rating">(.*)<~Ui';

    const IMDB_RUNTIME       = '~<td[^>]*>\s*Runtime\s*</td>\s*<td>(.+)</td>~Ui';
    const IMDB_SEARCH        = '~<td class="result_text"> <a href="\/title\/(tt\d{6,})\/(?:.*)"(?:\s*)>(?:.*)<\/a>~Ui';

    const IMDB_TITLE         = '~itemprop="name">(.*)(<\/h3>|<span)~Ui';
    const IMDB_TITLE_ORIG    = '~</h3>(?:\s+)(.*)(?:\s+)<span class=\"titlereference-original-title-label~Ui';
 
    const IMDB_USER_REVIEW   = '~href="/title/[t0-9]*/reviews"[^>]*>([^<]*)\s*User~Ui';
    const IMDB_VOTES         = '~"ipl-rating-star__total-votes">\s*\((.*)\)\s*<~Ui';
    const IMDB_WRITER        = '~<div[^>]*>\s*(?:Writer|Writers)\s*:\s*<ul[^>]*>(.+)</ul>~Ui';
    const IMDB_YEAR          = '~og:title\' content="(?:.*)\((?:.*)(\d{4})(?:.*)\)~Ui';

    /**
     * @var string The string returned, if nothing is found.
     */
    public static $sNotFound = 'n/A';

    /**
     * @var null|int The ID of the movie.
     */
    public $iId = null;

    /**
     * @var bool Is the content ready?
     */
    public $isReady = false;

    /**
     * @var string Char that separates multiple entries.
     */
    public $sSeparator = ' / ';

    /**
     * @var null|string The URL to the movie.
     */
    public $sUrl = null;

    /**
     * @var bool Return responses enclosed in array
     */
    public $bArrayOutput = false;

    /**
     * @var int Maximum cache time.
     */
    private $iCache = 1440;

    /**
     * @var null|string The root of the script.
     */
    private $sRoot = null;

    /**
     * @var null|string Holds the source.
     */
    private $sSource = null;

    /**
     * @var string What to search for?
     */
    private $sSearchFor = 'all';

    /**
     * @param string $sSearch    IMDb URL or movie title to search for.
     * @param null   $iCache     Custom cache time in minutes.
     * @param string $sSearchFor What to search for?
     *
     * @throws Exception
     */
    public function __construct($sSearch, $iCache = null, $sSearchFor = 'all')
    {
        $this->sRoot = dirname(__FILE__);
        if ( ! is_writable($this->sRoot . '/posters') && ! mkdir($this->sRoot . '/posters')) {
            throw new Exception('The directory “' . $this->sRoot . '/posters” isn’t writable.');
        }
        if ( ! is_writable($this->sRoot . '/cache') && ! mkdir($this->sRoot . '/cache')) {
            throw new Exception('The directory “' . $this->sRoot . '/cache” isn’t writable.');
        }
        if ( ! is_writable($this->sRoot . '/cast') && ! mkdir($this->sRoot . '/cast')) {
            throw new Exception('The directory “' . $this->sRoot . '/cast” isn’t writable.');
        }
        if ( ! function_exists('curl_init')) {
            throw new Exception('You need to enable the PHP cURL extension.');
        }
        if (in_array($sSearchFor,
                     [
                         'movie',
                         'tv',
                         'episode',
                         'game',
                         'all'
                     ])) {
            $this->sSearchFor = $sSearchFor;
        }
        if (true === self::IMDB_DEBUG) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(-1);
            echo '<pre><b>Running:</b> fetchUrl("' . $sSearch . '")</pre>';
        }
        if (null !== $iCache && (int) $iCache > 0) {
            $this->iCache = (int) $iCache;
        }
        $this->fetchUrl($sSearch);
    }

    /**
     * @param string $sSearch IMDb URL or movie title to search for.
     *
     * @return bool True on success, false on failure.
     */
    private function fetchUrl($sSearch)
    {
        $sSearch = trim($sSearch);

        // Try to find a valid URL.
        $sId = IMDBHelper::matchRegex($sSearch, self::IMDB_ID, 1);
        if (false !== $sId) {
            $this->iId  = preg_replace('~[\D]~', '', $sId);
            $this->sUrl = 'https://www.imdb.com/title/tt' . $this->iId . '/reference';
            $bSearch    = false;
        } else {
            switch (strtolower($this->sSearchFor)) {
                case 'movie':
                    $sParameters = '&s=tt&ttype=ft';
                    break;
                case 'tv':
                    $sParameters = '&s=tt&ttype=tv';
                    break;
                case 'episode':
                    $sParameters = '&s=tt&ttype=ep';
                    break;
                case 'game':
                    $sParameters = '&s=tt&ttype=vg';
                    break;
                default:
                    $sParameters = '&s=tt';
            }

            $this->sUrl = 'https://www.imdb.com/find?q=' . rawurlencode(str_replace(' ', '+', $sSearch)) . $sParameters;
            $bSearch    = true;

            // Was this search already performed and cached?
            $sRedirectFile = $this->sRoot . '/cache/' . sha1($this->sUrl) . '.redir';
            if (is_readable($sRedirectFile)) {
                if (self::IMDB_DEBUG) {
                    echo '<pre><b>Using redirect:</b> ' . basename($sRedirectFile) . '</pre>';
                }
                $sRedirect  = file_get_contents($sRedirectFile);
                $this->sUrl = trim($sRedirect);
                $this->iId  = preg_replace('~[\D]~', '', IMDBHelper::matchRegex($sRedirect, self::IMDB_ID, 1));
                $bSearch    = false;
            }
        }

        // Does a cache of this movie exist?
        $sCacheFile = $this->sRoot . '/cache/' . sha1($this->iId) . '.cache';
        if (is_readable($sCacheFile)) {
            $iDiff = round(abs(time() - filemtime($sCacheFile)) / 60);
            if ($iDiff < $this->iCache) {
                if (true === self::IMDB_DEBUG) {
                    echo '<pre><b>Using cache:</b> ' . basename($sCacheFile) . '</pre>';
                }
                $this->sSource = file_get_contents($sCacheFile);
                $this->isReady = true;

                return true;
            }
        }

        // Run cURL on the URL.
        if (true === self::IMDB_DEBUG) {
            echo '<pre><b>Running cURL:</b> ' . $this->sUrl . '</pre>';
        }

        $aCurlInfo = IMDBHelper::runCurl($this->sUrl);
        $sSource   = $aCurlInfo['contents'];

        if (false === $sSource) {
            if (true === self::IMDB_DEBUG) {
                echo '<pre><b>cURL error:</b> ' . var_dump($aCurlInfo) . '</pre>';
            }

            return false;
        }

        // Was the movie found?
        $sMatch = IMDBHelper::matchRegex($sSource, self::IMDB_SEARCH, 1);
        if (false !== $sMatch) {
            $sUrl = 'https://www.imdb.com/title/' . $sMatch . '/reference';
            if (true === self::IMDB_DEBUG) {
                echo '<pre><b>New redirect saved:</b> ' . basename($sRedirectFile) . ' => ' . $sUrl . '</pre>';
            }
            file_put_contents($sRedirectFile, $sUrl);
            $this->sSource = null;
            self::fetchUrl($sUrl);

            return true;
        }
        $sMatch = IMDBHelper::matchRegex($sSource, self::IMDB_NOT_FOUND, 0);
        if (false !== $sMatch) {
            if (true === self::IMDB_DEBUG) {
                echo '<pre><b>Movie not found:</b> ' . $sSearch . '</pre>';
            }

            return false;
        }

        $this->sSource = str_replace([
                                         "\n",
                                         "\r\n",
                                         "\r"
                                     ],
                                     '',
                                     $sSource);
        $this->isReady = true;

        // Save cache.
        if (false === $bSearch) {
            if (true === self::IMDB_DEBUG) {
                echo '<pre><b>Cache created:</b> ' . basename($sCacheFile) . '</pre>';
            }
            file_put_contents($sCacheFile, $this->sSource);
        }

        return true;
    }

   public function getAll()
    {
        $aData = [];
        foreach (get_class_methods(__CLASS__) as $method) {
            if (substr($method, 0, 3) === 'get' && $method !== 'getAll' && $method !== 'getCastImages') {
                $aData[$method] = [
                    'name'  => ltrim($method, 'get'),
                    'value' => $this->{$method}()
                ];
            }
        }
        array_multisort($aData);

        return $aData;
    }
  
    public function getAspectRatio()
    {
        if (true === $this->isReady) {
            $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_ASPECT_RATIO, 1);
            if (false !== $sMatch) {
                return IMDBHelper::cleanString($sMatch);
            }
        }

        return self::$sNotFound;
    }

    /**
     * @return string The awards of the movie or $sNotFound.
     */
    public function getAwards()
    {
        if (true === $this->isReady) {
            $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_AWARDS, 1);
            if (false !== $sMatch) {
                return IMDBHelper::cleanString($sMatch);
            }
        }

        return self::$sNotFound;
    }

    /**
     * @param string $sTarget Add a target to the links?
     *
     * @return string A list with linked countries or $sNotFound.
     */
    public function getCountry($sTarget = '')
    {
        if (true === $this->isReady) {
            $aMatch  = IMDBHelper::matchRegex($this->sSource, self::IMDB_COUNTRY);
            $aReturn = [];
            if (count($aMatch[2])) {
                foreach ($aMatch[2] as $i => $sName) {
                    $aReturn[] =
                        '<a href="https://www.imdb.com/country/' .
                        trim($aMatch[1][$i]) .
                        '/"' .
                        ($sTarget ? ' target="' . $sTarget . '"' : '') .
                        '>' .
                        IMDBHelper::cleanString($sName) .
                        '</a>';
                }

                return IMDBHelper::arrayOutput($this->bArrayOutput, $this->sSeparator, self::$sNotFound, $aReturn);
            }
        }

        return IMDBHelper::arrayOutput($this->bArrayOutput, $this->sSeparator, self::$sNotFound);
    }
   
    public function getCreator($sTarget = '')
    {
        if (true === $this->isReady) {
            $sMatch  = IMDBHelper::matchRegex($this->sSource, self::IMDB_CREATOR, 1);
            $aMatch  = IMDBHelper::matchRegex($sMatch, self::IMDB_NAME);
            $aReturn = [];
            if (count($aMatch[2])) {
                foreach ($aMatch[2] as $i => $sName) {
                    $aReturn[] =
                        '<a href="https://www.imdb.com/name/' .
                        IMDBHelper::cleanString($aMatch[1][$i]) .
                        '/"' .
                        ($sTarget ? ' target="' . $sTarget . '"' : '') .
                        '>' .
                        IMDBHelper::cleanString($sName) .
                        '</a>';
                }

                return IMDBHelper::arrayOutput($this->bArrayOutput, $this->sSeparator, self::$sNotFound, $aReturn);
            }
        }

        return IMDBHelper::arrayOutput($this->bArrayOutput, $this->sSeparator, self::$sNotFound);
    }

    /**
     * @return string The description of the movie or $sNotFound.
     */
    public function getDescription()
    {
        if (true === $this->isReady) {
            $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_DESCRIPTION, 1);
            if (false !== $sMatch) {
                return IMDBHelper::cleanString($sMatch);
            }
        }

        return self::$sNotFound;
    }
 
    public function getDirector($sTarget = '')
    {
        if (true === $this->isReady) {
            $sMatch  = IMDBHelper::matchRegex($this->sSource, self::IMDB_DIRECTOR, 1);
            $aMatch  = IMDBHelper::matchRegex($sMatch, self::IMDB_NAME);
            $aReturn = [];
            if (count($aMatch[2])) {
                foreach ($aMatch[2] as $i => $sName) {
                    $aReturn[] =
                        '<a href="https://www.imdb.com/name/' .
                        IMDBHelper::cleanString($aMatch[1][$i]) .
                        '/"' .
                        ($sTarget ? ' target="' . $sTarget . '"' : '') .
                        '>' .
                        IMDBHelper::cleanString($sName) .
                        '</a>';
                }

                return IMDBHelper::arrayOutput($this->bArrayOutput, $this->sSeparator, self::$sNotFound, $aReturn);
            }
        }

        return IMDBHelper::arrayOutput($this->bArrayOutput, $this->sSeparator, self::$sNotFound);
    }

    public function getGenre($sTarget = '')
    {
        if (true === $this->isReady) {
            $aMatch  = IMDBHelper::matchRegex($this->sSource, self::IMDB_GENRE);
            $aReturn = [];
            if (count($aMatch[2])) {
                foreach (array_unique($aMatch[2]) as $i => $sName) {
                    $aReturn[] =
                        '<a href="https://www.imdb.com/search/title?genres=' .
                        IMDBHelper::cleanString($aMatch[1][$i]) .
                        '"' .
                        ($sTarget ? ' target="' . $sTarget . '"' : '') .
                        '>' .
                        IMDBHelper::cleanString($sName) .
                        '</a>';
                }

                return IMDBHelper::arrayOutput($this->bArrayOutput, $this->sSeparator, self::$sNotFound, $aReturn);
            }
        }

        return IMDBHelper::arrayOutput($this->bArrayOutput, $this->sSeparator, self::$sNotFound);
    }
 
    public function getRating()
    {
        if (true === $this->isReady) {
            $sMatch = IMDBHelper::matchRegex($this->sSource, self::IMDB_RATING, 1);
            if (false !== $sMatch) {
                return IMDBHelper::cleanString($sMatch);
            }
        }

        return self::$sNotFound;
    } 
}
class IMDBHelper extends IMDB
{
    /**
     * Regular expression helper.
     *
     * @param string $sContent The content to search in.
     * @param string $sPattern The regular expression.
     * @param null   $iIndex   The index to return.
     *
     * @return bool   If no match was found.
     * @return string If one match was found.
     * @return array  If more than one match was found.
     */
    public static function matchRegex($sContent, $sPattern, $iIndex = null)
    {
        preg_match_all($sPattern, $sContent, $aMatches);
        if ($aMatches === false) {
            return false;
        }
        if ($iIndex !== null && is_int($iIndex)) {
            if (isset($aMatches[$iIndex][0])) {
                return $aMatches[$iIndex][0];
            }

            return false;
        }

        return $aMatches;
    }

    /**
     * Preferred output in responses with multiple elements
     *
     * @param bool   $bArrayOutput Native array or string with separators.
     * @param string $sSeparator   String separator.
     * @param string $sNotFound    Not found text.
     * @param array  $aReturn      Original input.
     * @param bool   $bHaveMore    Have more elements indicator.
     *
     * @return string|array Multiple results separated by selected separator string, or enclosed into native array.
     */
    public static function arrayOutput($bArrayOutput, $sSeparator, $sNotFound, $aReturn = null, $bHaveMore = false)
    {
        if ($bArrayOutput) {
            if ($aReturn == null || ! is_array($aReturn)) {
                return [];
            }

            if ($bHaveMore) {
                $aReturn[] = '…';
            }

            return $aReturn;
        } else {
            if ($aReturn == null || ! is_array($aReturn)) {
                return $sNotFound;
            }

            foreach ($aReturn as $i => $value) {
                if (is_array($value)) {
                    $aReturn[$i] = implode($sSeparator, $value);
                }
            }

            return implode($sSeparator, $aReturn) . (($bHaveMore) ? '…' : '');
        }
    }

    /**
     * @param string $sInput Input (eg. HTML).
     *
     * @return string Cleaned string.
     */
    public static function cleanString($sInput)
    {
        $aSearch  = [
            'Full summary &raquo;',
            'Full synopsis &raquo;',
            'Add summary &raquo;',
            'Add synopsis &raquo;',
            'See more &raquo;',
            'See why on IMDbPro.',
            "\n",
            "\r"
        ];
        $aReplace = [
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        $sInput   = str_replace('</li>', ' | ', $sInput);
        $sInput   = strip_tags($sInput);
        $sInput   = str_replace('&nbsp;', ' ', $sInput);
        $sInput   = str_replace($aSearch, $aReplace, $sInput);
        $sInput   = html_entity_decode($sInput, ENT_QUOTES | ENT_HTML5);
        $sInput   = preg_replace('/\s+/', ' ', $sInput);
        $sInput   = trim($sInput);
        $sInput   = rtrim($sInput, ' |');

        return ($sInput ? trim($sInput) : self::$sNotFound);
    }

    public static function runCurl($sUrl, $bDownload = false)
    {
        $oCurl = curl_init($sUrl);
        curl_setopt_array($oCurl,
                          [
                              CURLOPT_BINARYTRANSFER => ($bDownload ? true : false),
                              CURLOPT_CONNECTTIMEOUT => self::IMDB_TIMEOUT,
                              CURLOPT_ENCODING       => '',
                              CURLOPT_FOLLOWLOCATION => 0,
                              CURLOPT_FRESH_CONNECT  => 0,
                              CURLOPT_HEADER         => ($bDownload ? false : true),
                              CURLOPT_HTTPHEADER     => [
                                  'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                                  'Accept-Charset: utf-8, iso-8859-1;q=0.5',
                                  'Accept-Language: ' . self::IMDB_LANG
                              ],
                              CURLOPT_REFERER        => 'https://www.imdb.com',
                              CURLOPT_RETURNTRANSFER => 1,
                              CURLOPT_SSL_VERIFYHOST => 0,
                              CURLOPT_SSL_VERIFYPEER => 0,
                              CURLOPT_TIMEOUT        => self::IMDB_TIMEOUT,
                              CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:59.0) Gecko/20100101 Firefox/59.0',
                              CURLOPT_VERBOSE        => 0
                          ]);
        $sOutput   = curl_exec($oCurl);
        $aCurlInfo = curl_getinfo($oCurl);
        curl_close($oCurl);
        $aCurlInfo['contents'] = $sOutput;

        if (200 !== $aCurlInfo['http_code'] && 302 !== $aCurlInfo['http_code']) {
            if (true === self::IMDB_DEBUG) {
                echo '<pre><b>cURL returned wrong HTTP code “' . $aCurlInfo['http_code'] . '”, aborting.</b></pre>';
            }
            return false;
        }
        return $aCurlInfo;
    }
}
