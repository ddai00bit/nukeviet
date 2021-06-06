<?php

/**
 * @Project NUKEVIET 4.x
 * @Author VINADES.,JSC <contact@vinades.vn>
 * @Copyright (C) 2014 VINADES.,JSC. All rights reserved
 * @License GNU/GPL version 2 or any later version
 * @Createdate 3-6-2010 0:14
 */

if (!defined('NV_IS_MOD_NEWS')) {
    die('Stop!!!');
}

$alias_cat_url = $array_op[1];
$array_page = explode('-', $array_op[2]);
$id = intval(end($array_page));
$catid = 0;
foreach ($global_array_cat as $catid_i => $array_cat_i) {
    if ($alias_cat_url == $array_cat_i['alias']) {
        $catid = $catid_i;
        break;
    }
}

$reCaptchaPass = (!empty($global_config['recaptcha_sitekey']) and !empty($global_config['recaptcha_secretkey']) and ($global_config['recaptcha_ver'] == 2 or $global_config['recaptcha_ver'] == 3));

if ($id > 0 and $catid > 0) {
    $sql = 'SELECT id, title, alias, hometext FROM ' . NV_PREFIXLANG . '_' . $module_data . '_' . $catid . ' WHERE id =' . $id . ' AND status=1';
    $result = $db_slave->query($sql);
    list ($id, $title, $alias, $hometext) = $result->fetch(3);
    if ($id > 0) {
        $checkss = $nv_Request->get_string('checkss', 'post', '');
        if ($checkss == md5($id . NV_CHECK_SESSION)) {
            $allowed_send = $db_slave->query('SELECT allowed_send FROM ' . NV_PREFIXLANG . '_' . $module_data . '_detail WHERE id=' . $id)->fetchColumn();
            if ($allowed_send == 1) {
                $your_name = $your_email = '';
                if (defined('NV_IS_ADMIN')) {
                    $your_name = $admin_info['username'];
                    $your_email = $admin_info['email'];
                } elseif (defined('NV_IS_USER')) {
                    $your_name = $user_info['username'];
                    $your_email = $user_info['email'];
                }
                if ($nv_Request->isset_request('send', 'post')) {
                    if ($module_config[$module_name]['scaptcha_type'] == 'recaptcha' and $reCaptchaPass) {
                        $nv_seccode = $nv_Request->get_title('g-recaptcha-response', 'post', '');
                    } elseif ($module_config[$module_name]['scaptcha_type'] == 'captcha') {
                        $nv_seccode = $nv_Request->get_title('nv_seccode', 'post', '');
                    }
                    
                    if (($module_config[$module_name]['scaptcha_type'] == 'captcha' or ($module_config[$module_name]['scaptcha_type'] == 'recaptcha' and $reCaptchaPass)) and !nv_capcha_txt($nv_seccode, $module_config[$module_name]['scaptcha_type'])) {
                        nv_jsonOutput([
                            'status' => 'error',
                            'input' => 'nv_seccode',
                            'mess' => $lang_global['securitycodeincorrect']
                        ]);
                    }
                    
                    $friend_email = $nv_Request->get_title('friend_email', 'post', '');
                    if (($friend_email_error = nv_check_valid_email($friend_email)) != '') {
                        nv_jsonOutput([
                            'status' => 'error',
                            'input' => 'friend_email',
                            'mess' => $friend_email_error
                        ]);
                    }

                    $your_name = $nv_Request->get_title('your_name', 'post', '');
                    $_t = str_replace("&#039;", "'", $your_name);
                    if (!preg_match('/^([\p{L}\p{Mn}\p{Pd}\'][\p{L}\p{Mn}\p{Pd}\',\s]*)*$/u', $_t)) {
                        nv_jsonOutput([
                            'status' => 'error',
                            'input' => 'your_name',
                            'mess' => $lang_module['sendmail_err_name']
                        ]);
                    }
                    
                    $difftimeout = 3600;
                    $dir = NV_ROOTDIR . '/' . NV_LOGS_DIR . '/news_logs';
                    $log_fileext = preg_match('/^[a-z]+$/i', NV_LOGS_EXT) ? NV_LOGS_EXT : 'log';
                    $pattern = '/^(.*)\.' . $log_fileext . '$/i';
                    $logs = nv_scandir($dir, $pattern);
                
                    if (!empty($logs)) {
                        foreach ($logs as $file) {
                            $vtime = filemtime($dir . '/' . $file);
                
                            if (!$vtime or $vtime <= NV_CURRENTTIME - $difftimeout) {
                                @unlink($dir . '/' . $file);
                            }
                        }
                    }

                    $logfile = 'sf' . $id . '_' . md5(NV_LANG_DATA . $global_config['sitekey'] . $friend_email) . '.' . $log_fileext;
                    if (file_exists($dir . '/' . $logfile)) {
                        $timeout = filemtime($dir . '/' . $logfile);
                        $timeout = ceil(($difftimeout - NV_CURRENTTIME + $timeout) / 60);
                        nv_jsonOutput([
                            'status' => 'OK',
                            'mess' => sprintf($lang_module['sendmail_limit_sendmail'], $friend_email, $timeout)
                        ]);
                    }

                    $your_message = $nv_Request->get_title('your_message', 'post', '', 1);
                    !empty($your_message) && $your_message = preg_replace('/([a-z0-9][a-z0-9-]{1,61}[a-z0-9])(\.[a-z]{2,})+/i', '***', $your_message);

                    $link = nv_url_rewrite(NV_BASE_SITEURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&amp;' . NV_NAME_VARIABLE . '=' . $module_name . '&amp;' . NV_OP_VARIABLE . '=' . $global_array_cat[$catid]['alias'] . '/' . $alias . '-' . $id . $global_config['rewrite_exturl'], true);
                    if (!str_starts_with($link, NV_MY_DOMAIN)) {
                        $link = NV_MY_DOMAIN . $link;
                    }
                    $link = '<a href="' . $link . '" title="' . $title . '">' . $link . '</a>';

                    if (empty($hometext)) {
                        $hometext = $db_slave->query('SELECT bodyhtml FROM ' . NV_PREFIXLANG . '_' . $module_data . '_detail WHERE id =' . $id)->fetchColumn();
                        $hometext = nv_clean60(strip_tags(str_replace(["\r\n", "\r", "\n"], " ", $hometext)), 300);
                    }
                    
                    $subject = sprintf($lang_module['sendmail_subject'], $your_name);
                    $message = !empty($your_message) ? sprintf($lang_module['sendmail_welcome1'], $your_name, $title, $global_config['site_name'], $your_message) : sprintf($lang_module['sendmail_welcome'], $your_name, $title, $global_config['site_name']);
                    $message .= '<br/>----------<br/><strong>' . $title . '</strong><br/>' . $hometext . '<br/><br/>';
                    $message .= sprintf($lang_module['sendmail_welcome2'], $link);

                    if (!empty($your_email)) {
                        $from = [
                            $your_name,
                            $your_email
                        ];
                    } else {
                        $from = [
                            $global_config['site_name'],
                            $global_config['site_email']
                        ];
                    }
                    
                    $check = nv_sendmail($from, $friend_email, $subject, $message);
                    if ($check) {
                        file_put_contents($dir . '/' . $logfile, '', LOCK_EX);
                        nv_jsonOutput([
                            'status' => 'OK',
                            'mess' => sprintf($lang_module['sendmail_success'], $friend_email)
                        ]);
                    } else {
                        nv_jsonOutput([
                            'status' => 'error',
                            'mess' => $lang_module['sendmail_success_err']
                        ]);
                    }
                }

                $sendmail = [
                    'checkss' => md5($id . NV_CHECK_SESSION),
                    'your_name' => $your_name,
                    'your_email' => $your_email,
                    'action' => nv_url_rewrite(NV_BASE_SITEURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&amp;' . NV_NAME_VARIABLE . '=' . $module_name . '&amp;' . NV_OP_VARIABLE . '=sendmail/' . $global_array_cat[$catid]['alias'] . '/' . $alias . '-' . $id . $global_config['rewrite_exturl'], true) //
                ];
    
                $contents = sendmail_themme($sendmail);
                nv_htmlOutput($contents);
            }
        }
    }
}
nv_redirect_location($global_config['site_url']);
