<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Validator\EmailValidator;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Validation\EmailValidation;

class NoRFCEmailValidator extends EmailValidator
{
    /**
     * @param $email
     * @param EmailValidation $emailValidation
     *
     * @return bool
     */
    public function isValid($email, EmailValidation $emailValidation)
    {
        $wsp = '[\x20\x09]';
        $vchar = '[\x21-\x7e]';
        $quoted_pair = "\\\\(?:$vchar|$wsp)";
        $qtext = '[\x21\x23-\x5b\x5d-\x7e]';
        $qcontent = "(?:$qtext|$quoted_pair)";
        $quoted_string = "\"$qcontent*\"";
        $atext = '[a-zA-Z0-9!#$%&\'*+\-\/\=?^_`{|}~]';
        $dot_atom = "$atext+(?:[.]$atext+)*";
        $local_part = "(?:$dot_atom|$quoted_string)";
        $domain = $dot_atom;
        $addr_spec = "{$local_part}[@]$domain";
        $dot_atom_loose = "$atext+(?:[.]|$atext)*";
        $local_part_loose = "(?:$dot_atom_loose|$quoted_string)";
        $addr_spec_loose = "{$local_part_loose}[@]$domain";

        // 携帯メールアドレス用に、..や.@を許容する。
        $regexp = "/\A{$addr_spec_loose}\z/";

        if (preg_match($regexp, $email)) {
            return true;
        }

        return false;
    }
}
