<?php

/**
 * query_builder.php
 *
 * SQL Queryの組み立てクラス
 *
 * ※ web, batch, trackでの共通クラス
 * ※ 修正した場合は各プロジェクトに当ファイルをコピーして反映させること
 *
 * $Id: query_builder.php 515 2010-07-20 09:31:45Z kagen $
 */
require_once VENDORS . 'Constant/CommonConst.php';
config('database');

class QueryBuilder {

    private $mRawQuery;
    private $mProcQuery;
    private $mWhere;
    private $mValue = array();
    private $mOrder;
    private $mReplaceRule = array();
    private $mPageNum = 0;
    private $mPageSize = 0;
    private $mDebugMod = false;
    private $object;
    private $isOutputQueryLog = false;

    const SORT_KEY = "sort";

    private $mStatement = "";
    private $dbcon = null;
    private static $dbConnect = NULL;

    function __construct() {
        $this->object = new Object();

        // SQLログ出力
        if (defined('__QUERY_LOG_OUTPUT__') && __QUERY_LOG_OUTPUT__) {
            $this->isOutputQueryLog = true;
        } else {
            $this->isOutputQueryLog = false;
        }
        if (empty(self::$dbConnect)) {
            self::$dbConnect = $this->getDatabaseObj(CommonConst::getDbConnectTarget());
        }
    }

    /**
     * Close current connection
     */
    public static function closeConnection() {
       if(!empty(self::$dbConnect)){
             mysqli_close(self::$dbConnect);
        self::$dbConnect = NULL;
       }
      
    }

    /**
     * DebugModを設定
     *
     * @param $blnDebugMod デバグモードフラグ
     * @return
     */
    public function setDebugMod($blnDebugMod = true) {
        $this->mDebugMod = $blnDebugMod;
    }

    /**
     * DebugModを取得
     *
     * @param
     * @return　デバグモードフラグ
     */
    public function getDebugMod() {
        return $this->mDebugMod;
    }

    /**
     * 生Queryを設定
     *
     * @param　クエリー
     * @return
     */
    public function setQuery($strQuery) {
        $this->mRawQuery = $strQuery;
        $this->mProcQuery = $this->mRawQuery;
    }

    /**
     * WHERE条件連想配列を設定(SORTもOK)
     *
     * @param　WHERE条件連想配列
     * @return
     */
    public function setValue($vntValue) {
        $this->mValue = $vntValue;
    }

    /**
     * WHERE条件連想配列の追加
     *
     * @param　WHERE条件連想配列
     * @return
     */
    public function addValue($key, $value) {
        $tmp = $this->mValue;  // 現在のWHERE条件連想配列を取得
        $tmp[$key] = $value;   // 条件値追加
        $this->mValue = $tmp;
    }

    /**
     * ソート条件の連想配列を設定
     *
     * @param　ソート条件の連想配列
     * @return
     */
    public function setOrderPattern($vntOrderPattern) {
        $this->mOrder = $vntOrderPattern;
    }

    /**
     * ソート条件キーを設定
     *
     * @param　ソート条件の連想配列キー
     * @return
     */
    public function setOrder($mOrder) {
        $this->mValue[self::SORT_KEY] = $mOrder;
    }

    /**
     * ソートパターン設定を追加
     *
     * @param　ソート条件の連想配列キー
     * @return
     */
    public function addOrderPattern($vntOrderPattern) {
        $this->mOrder = array_merge($this->mOrder, $vntOrderPattern);
    }

    /**
     * 置換ルール設定を追加
     *
     * 連想配列との置換文字列の変換処理時に「'」をつけるかつけないかの設定
     * 　文字列：select id from table where id={id} and flg={flg}
     * 　置換値：array('id' => '12', flg => '1')
     * 　ルール：array('id'=>false)　（本関数の引数：falseを設定した要素は「'」を付加しない）
     * 　置換結果：select id from table where id=12 and flg='1' (buildQueryで得られる値）
     *
     * @param　ソート条件の連想配列キー
     * @return
     */
    public function setReplaceRule($rule) {
        if (is_array($rule)) {
            $this->mReplaceRule = $rule;
        }
    }

    public function resetReplaceRule() {
        setReplaceRule(array());
    }

    /**
     * 現在ページとページ件数を設定
     *
     * @param　ページとページ件数配列
     * @return
     */
    public function setPager($intPageNum, $intPageSize) {
        $this->mPageNum = $intPageNum;
        $this->mPageSize = $intPageSize;
    }

    /**
     * 現在ページとページ件数を初期化
     *
     * @param　なし
     * @return　なし
     */
    public function resetPager() {
        $this->mPageNum = 0;
        $this->mPageSize = 0;
    }

    /**
     * 未加工Queryを返す
     *
     * @param
     * @return　未加工Query
     */
    public function getRawQuery() {
        return $this->mRawQuery;
    }

    /**
     * WHERE条件を返す
     *
     * @param
     * @return　WHERE条件連想配列
     */
    public function getValue() {
        return $this->mValue;
    }

    /**
     * ソートパターンを返す
     *
     * @param
     * @return　ソートパターン連想配列
     */
    public function getOrder() {
        if (is_array($this->mValue)) {
            if (array_key_exists(self::SORT_KEY, $this->mValue)) {
                if (is_array($this->mOrder)) {
                    if (array_key_exists($this->mValue[self::SORT_KEY], $this->mOrder)) {
                        return $this->mOrder[$this->mValue[self::SORT_KEY]];
                    }
                }
            }
        }
        return false;
    }

    /**
     * 現在ページを返す
     *
     * @param
     * @return　現在ページ数
     */
    public function getPageNum() {
        return $this->mPageNum;
    }

    /**
     * ページ表示件数を返す
     *
     * @param
     * @return　ページ表示件数
     */
    public function getPageSize() {
        return $this->mPageSize;
    }

    /**
     * クエリを組み立てて返す
     *
     * @param $flg ログ出力制御　true/false
     * @param $queryCheck クエリチェック({ }が入っていたらcakeError)を行う(true)/行わない(false)
     * @return　クエリーまたはFALSEを返す
     */
    public function getQuery($flg = true, $queryCheck = false) {
        $this->mStatement = $this->buildQuery($flg);

        if ($queryCheck) {
            // { }が残っている場合、cakeError
            if (false !== strpos($this->mStatement, '{') || false !== strpos($this->mStatement, '}')) {
                $this->object->log('QUERY ERROR:' . $this->mStatement);
                $this->object->cakeError('illegalParameter', $this->mStatement);
            }
        }
        return $this->mStatement;
    }

    /**
     * クエリを組み立て
     *
     * @param $flg ログ出力制御　true/false
     * @return　組み立て成功ならクエリーを返す、失敗ならFALSEを返す
     */
    private function buildQuery($flg = false) {

        //まずはmProcQueryを初期化
        $this->mProcQuery = $this->mRawQuery;

        //行頭に（select|insert|update|delete）がなければ、false
        ////SELECTさえ無かったらfalse
        //if(stripos(strval($this->mProcQuery),"SELECT ") === false){
        //	return false;
        //}

        if ($this->mDebugMod == true) {
            //Debugモード中一部SQLコマンドを大文字にします、予約語を追加してください
            $this->mProcQuery = str_ireplace("SELECT ", "SELECT ", $this->mProcQuery);
            $this->mProcQuery = str_ireplace(" DISTINCT ", " DISTINCT ", $this->mProcQuery);
            $this->mProcQuery = str_ireplace(" FROM ", " FROM ", $this->mProcQuery);
            $this->mProcQuery = str_ireplace(" LEFT ", " LEFT ", $this->mProcQuery);
            $this->mProcQuery = str_ireplace(" RIGHT ", " RIGHT ", $this->mProcQuery);
            $this->mProcQuery = str_ireplace(" INNER ", " INNER ", $this->mProcQuery);
            $this->mProcQuery = str_ireplace(" WHERE ", " WHERE ", $this->mProcQuery);
            $this->mProcQuery = str_ireplace(" AND ", " AND ", $this->mProcQuery);
            $this->mProcQuery = str_ireplace(" OR ", " OR ", $this->mProcQuery);
        }

        //WHERE条件組み立て
        foreach ($this->mValue as $key => $value) {
            //ソート条件をWHEREから除外する
            if (!is_array($value)) {
                if ($key !== self::SORT_KEY) {
                    $value = mysqli_real_escape_string(self::$dbConnect, $value);
                    if (array_key_exists($key, $this->mReplaceRule) && $this->mReplaceRule[$key] === false) {
                        $this->mProcQuery = str_ireplace('{' . $key . '}', ($value == null ? '' : $value), $this->mProcQuery);
                    } else {
                        $this->mProcQuery = str_ireplace('{' . $key . '}', ($value == null ? 'NULL' : '\'' . $value . '\''), $this->mProcQuery);
                    }
                }
            }
        }

        //ソート条件組み立て
        if (array_key_exists(self::SORT_KEY, $this->mValue)) {
            if (is_array($this->mOrder)) {
                if (array_key_exists($this->mValue[self::SORT_KEY], $this->mOrder)) {
                    $this->mProcQuery .= " ORDER BY " . $this->mOrder[$this->mValue[self::SORT_KEY]];
                }
            }
        }

        //ページをつける
        if ($this->mPageSize > 0) {
            if ($this->mPageNum < 1) {
                $this->mPageNum = 1;
            }
            if ($this->mPageNum > 1) {
                $intTmp = ($this->mPageNum - 1) * $this->mPageSize;
                $strTmp = " LIMIT " . $intTmp . "," . $this->mPageSize;
            } else {
                $strTmp = " LIMIT " . $this->mPageSize;
            }
            $this->mProcQuery .= $strTmp;
        }


        // クエリ文のログを出力する
        //--------------------

        $query = preg_replace(array("/\r\n/", "/\s+/"), " ", $this->mProcQuery);
        $query = (substr($this->mProcQuery, -1) == ";") ? $this->mProcQuery : $this->mProcQuery . ";";

        return $this->mProcQuery;
    }

    /**
     * DB独自接続
     * 　ModelでのPDO接続ではなく、独自にDB接続を行う。
     * 　 接続DB情報はModelから取得
     *
     * @return 接続が成功した場合、DB接続リソース
     */
    function getDatabaseObj($dbname) {
        // 独自DB接続
        $dbconf = new DATABASE_CONFIG();
        $host = $dbconf->{$dbname}['host'];
        $user = $dbconf->{$dbname}['login'];
        $pass = $dbconf->{$dbname}['password'];
        $db = $dbconf->{$dbname}['database'];

        $dbcon = mysqli_connect($host, $user, $pass);
        mysqli_set_charset($dbcon, $dbconf->{$dbname}['encoding']);
        if (!$dbcon) {
            $this->object->log("[DB:" . $dbname . "] connection failed", LOG_DEBUG);
            throw new Exception('[DB:' . $dbname . '] connection failed. missingConnection');
        } else if (!mysqli_select_db($dbcon, $db)) {
            throw new Exception('[DB:' . $dbname . '] select failed missingConnection');
        }

        return $dbcon;
    }

    function escapeValue($params) {
        if (is_null($params) || $params === '') {
            return 'NULL';
        }
        return '\'' . mysqli_real_escape_string(self::$dbConnect, $params) . '\'';
    }

}

?>
