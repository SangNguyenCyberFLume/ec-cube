<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2010 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

/**
 * 会員情報の登録・編集・検索ヘルパークラス.
 *
 *
 * @package Helper
 * @author Hirokazu Fukuda
 * @version $Id$
 */
class SC_Helper_Customer {
    
    
    /**
     * 会員編集登録処理を行う.
     *
     * @param array $array パラメータの配列
     * @param array $arrRegistColumn 登録するカラムの配列
     * @return void
     * @deprecated 
     * @todo sfEditCustomerData に統一。LC_Page_Admin_Customer_Edit から呼び出されているだけ
     */
    function sfEditCustomerDataAdmin($array, $arrRegistColumn) {
        $objQuery =& SC_Query::getSingletonInstance();

        foreach ($arrRegistColumn as $data) {
            if ($data["column"] != "password" && $data["column"] != "reminder_answer" ) {
                if($array[ $data['column'] ] != "") {
                    $arrRegist[ $data["column"] ] = $array[ $data["column"] ];
                } else {
                    $arrRegist[ $data['column'] ] = NULL;
                }
            }
        }
        if (strlen($array["year"]) > 0 && strlen($array["month"]) > 0 && strlen($array["day"]) > 0) {
            $arrRegist["birth"] = $array["year"] ."/". $array["month"] ."/". $array["day"] ." 00:00:00";
        } else {
            $arrRegist["birth"] = NULL;
        }

        //-- パスワードの更新がある場合は暗号化。（更新がない場合はUPDATE文を構成しない）
        $salt = "";
        if ($array["password"] != DEFAULT_PASSWORD) {
            $salt = SC_Utils_Ex::sfGetRandomString(10);
            $arrRegist["salt"] = $salt;
            $arrRegist["password"] = SC_Utils_Ex::sfGetHashString($array["password"], $salt);
        }
        if ($array["reminder_answer"] != DEFAULT_PASSWORD) {
            if($salt == "") {
                $salt = $objQuery->get("salt", "dtb_customer", "customer_id = ? ", array($array['customer_id']));
            }
            $arrRegist["reminder_answer"] = SC_Utils_Ex::sfGetHashString($array["reminder_answer"], $salt);
        }
        
        $arrRegist["update_date"] = "NOW()";
        
        //-- 編集登録実行
        $objQuery->update("dtb_customer", $arrRegist, "customer_id = ? ", array($array['customer_id']));
    }
    
    /**
     * 会員編集登録処理を行う.
     *
     * @param array $array 登録するデータの配列（SC_FormParamのgetDbArrayの戻り値）
     * @param array $customer_id nullの場合はinsert, 存在する場合はupdate
     * @access public
     * @return integer 登録編集したユーザーのcustomer_id
     */
    function sfEditCustomerData($array, $customer_id = null) {
        $objQuery =& SC_Query::getSingletonInstance();

        $array["update_date"] = "now()";    // 更新日
        
        //-- パスワードの更新がある場合は暗号化
        $salt = "";
        if ($array["password"] != DEFAULT_PASSWORD) {
            $salt = SC_Utils_Ex::sfGetRandomString(10);
            $array["salt"] = $salt;
            $array["password"] = SC_Utils_Ex::sfGetHashString($array["password"], $salt);
        } else {
            unset($array["password"]);
        }
        if ($array["reminder_answer"] != DEFAULT_PASSWORD) {
            if(is_numeric($customer_id) and $salt == "") {
                $salt = $objQuery->get("salt", "dtb_customer", "customer_id = ? ", array($array['customer_id']));
            }
            $array["reminder_answer"] = SC_Utils_Ex::sfGetHashString($array["reminder_answer"], $salt);
        }
       
        //-- 編集登録実行
        if (is_numeric($customer_id)){
            // 編集
            $objQuery->update("dtb_customer", $array, "customer_id = ? ", array($customer_id));
        } else {
            // 新規登録
            
            // 会員ID
            $customer_id = $objQuery->nextVal('dtb_customer_customer_id');
            if (is_null($array["customer_id"])){
                $array['customer_id'] = $customer_id;
            }
            // 作成日
            if (is_null($array["create_date"])){
                $array["create_date"] = "now()"; 	
            }            
            $objQuery->insert("dtb_customer", $array);
        }
        return $customer_id;
    }
        
    /**
     * 注文番号、利用ポイント、加算ポイントから最終ポイントを取得する.
     *
     * @param integer $order_id 注文番号
     * @param integer $use_point 利用ポイント
     * @param integer $add_point 加算ポイント
     * @return array 最終ポイントの配列
     */
    function sfGetCustomerPoint($order_id, $use_point, $add_point) {
        $objQuery =& SC_Query::getSingletonInstance();
        $arrRet = $objQuery->select("customer_id", "dtb_order", "order_id = ?", array($order_id));
        $customer_id = $arrRet[0]['customer_id'];
        if ($customer_id != "" && $customer_id >= 1) {
            if (USE_POINT !== false) {
                $arrRet = $objQuery->select("point", "dtb_customer", "customer_id = ?", array($customer_id));
                $point = $arrRet[0]['point'];
                $total_point = $arrRet[0]['point'] - $use_point + $add_point;
            } else {
                $total_point = 0;
                $point = 0;
            }
        } else {
            $total_point = "";
            $point = "";
        }
        return array($point, $total_point);
    }
    
    /**
     *   emailアドレスから、登録済み会員や退会済み会員をチェックする
     *	 
     *	 @param string $email  メールアドレス
     *   @return integer  0:登録可能     1:登録済み   2:再登録制限期間内削除ユーザー  3:自分のアドレス
     */
    function lfCheckRegisterUserFromEmail($email){
        $return = 0;
        
        $objCustomer = new SC_Customer();
        $objQuery =& SC_Query::getSingletonInstance();
        
        $arrRet = $objQuery->select("email, update_date, del_flg"
                                    ,"dtb_customer"
                                    ,"email = ? OR email_mobile = ? ORDER BY del_flg"
                                    ,array($email, $email)
                                    );

        if(count($arrRet) > 0) {
            if($arrRet[0]['del_flg'] != '1') {
                // 会員である場合
                if (!isset($objErr->arrErr['email'])) $objErr->arrErr['email'] = "";
                $return = 1;
            } else {
                // 退会した会員である場合
                $leave_time = SC_Utils_Ex::sfDBDatetoTime($arrRet[0]['update_date']);
                $now_time = time();
                $pass_time = $now_time - $leave_time;
                // 退会から何時間-経過しているか判定する。
                $limit_time = ENTRY_LIMIT_HOUR * 3600;
                if($pass_time < $limit_time) {
                    if (!isset($objErr->arrErr['email'])) $objErr->arrErr['email'] = "";
                    $return = 2;
                }
            }
        }
        
        // ログインしている場合、すでに登録している自分のemailの場合はエラーを返さない
        if ($objCustomer->getValue('customer_id')){
            $arrRet = $objQuery->select("email, email_mobile"
                            ,"dtb_customer"
                            ,"customer_id = ? ORDER BY del_flg"
                            ,array($objCustomer->getValue('customer_id'))
                            );
            if ($email == $arrRet[0]["email"] 
                || $email == $arrRet[0]["email_mobile"]){
                    $return = 3;
                }
        }
        return $return;
    }
}