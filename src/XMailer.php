<?php

/**
 * 基于 PHPMailer + config 的封装
 * 配置基于 config.php [mailer]
 */

namespace Varobj\XP;

use Varobj\XP\Exception\UsageErrorException;
use Phalcon\Di;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class XMailer
{
    use Instance;

    protected $mailer;

    public function __construct(string $fromName = '')
    {
        $config = Di::getDefault()->getShared('config');
        $flag = 0;
        empty($config->mailer) and $flag = 1;
        !$flag and empty($config->mailer->email) and $flag = 2;
        !$flag and empty($config->mailer->password) and $flag = 3;
        $pwd = !$flag ? $config->mailer->password : '';
        !$flag and $pwd === '{ENV}' and !($pwd = env('mailer.password')) and $flag = 4;
        $email = !$flag ? $config->mailer->email : '';
        !$flag and $email === '{ENV}' and !($email = env('mailer.eamil')) and $flag = 5;
        if ($flag) {
            throw new UsageErrorException('发送邮件服务配置错误[' . $flag . ']');
        }
        $this->mailer = new PHPMailer(true);

        // Server 配置
        $this->mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $this->mailer->SMTPDebug = is_prod() ? SMTP::DEBUG_OFF : SMTP::DEBUG_LOWLEVEL;
        if ($this->mailer->SMTPDebug > 0) {
            $this->mailer->Debugoutput = function ($str) {
//                echo $str . PHP_EOL;
            };
        }
        $this->mailer->isSMTP();
        $this->mailer->Host = 'smtp.exmail.qq.com';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $email;
        $this->mailer->Password = $pwd;
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $this->mailer->Port = 465;

        // 通用发送配置
        $this->mailer->setFrom($email, $fromName);
    }

    public function getMailer(): PHPMailer
    {
        return $this->mailer;
    }
}