<?php

namespace App\Service\Common;

use App\Foundation\Utils\Mail;
use App\Service\BaseService;

class EmailService extends BaseService
{
    public function send($email = '', $username = '', $code = '')
    {
        return Mail::init()->setFromAddress('notifynotify@tradingking.vip', 'Tradingking')
            ->setAddress($email, $username)
            ->setSubject('重要操作验证码')
            ->setBody($this->getEmailHtml($code))
            ->send();
    }

    /**
     * 获取Email模板
     * @param string $code
     * @return string
     */
    private function getEmailHtml(string $code): string
    {
        return '<body style="color: #666; font-size: 14px; font-family: \'Open Sans\',Helvetica,Arial,sans-serif;">
                    <div class="box-content" style="width: 80%; margin: 20px auto; max-width: 800px; min-width: 600px;">
                        <div class="info-wrap" style="border-bottom-left-radius: 10px;border-bottom-right-radius: 10px;
                                                  border:1px solid #ddd;
                                                  overflow: hidden;
                                                  padding: 15px 15px 20px;">
                            <div class="tips" style="padding:15px;">
                                <p style=" list-style: 160%; margin: 10px 0;">【TK】操作提示</p>
                                <p style=" list-style: 160%; margin: 10px 0;">您的验证码为：' . $code . '</p>
                                <p style=" list-style: 160%; margin: 10px 0;">验证码将在5分钟后失效，切勿告知他人。</p>
                                <br>
                                <p style=" list-style: 160%; margin: 10px 0;"><i>这是一条自动发送的消息，请勿回复</i></p>
                            </div>
                            --------------------------------------------------------
                            <div class="tips" style="padding:15px;">
                                <p style=" list-style: 160%; margin: 10px 0;">[TK]System Notification</p>
                                <p style=" list-style: 160%; margin: 10px 0;">Your verification code is:' . $code . '</p>
                                <p style=" list-style: 160%; margin: 10px 0;">The verification code will expire in 5 minutes, do not tell others.</p>
                                <br>
                                <p style=" list-style: 160%; margin: 10px 0;"><i>This is an automatic message, please do not reply</i></p>
                            </div>
                            <table class="list" style="width: 100%; border-collapse: collapse; border-top:1px solid #eee; font-size:12px;">
                            </table>
                        </div>
                    </div>
                </body>';
    }
}