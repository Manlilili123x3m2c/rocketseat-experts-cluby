<?php

declare(strict_types=1);

namespace Core\Plugins;

use App\Constants\StatusCode;
use App\Exception\BusinessException;
use Core\Common\Container\Auth;
use Core\Services\AttachmentService;
use Hyperf\Di\Annotation\Inject;
use HyperfLibraries\Sms\Contract\SmsInterface;
use Hyperf\Utils\ApplicationContext;
use App\Models\Sms as SmsModel;
use Hyperf\Logger\LoggerFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use App\Event\SmsEvent;

/**
 * 短信发送/验证类
 * Class Sms
 * @package Core\Plugins
 * author MengShuai <133814250@qq.com>
 * date 2021/01/13 14:02
 *
 * @property SmsModel $SmsModel
 */
class Sms
{

    /**
     * @Inject()
     * @var SmsModel
     */
    protected $SmsModel;

    /**
     * @Inject
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;


    /**
     * 验证码有效时长
     * @var int
     */
    protected static $expire = 120;

    /**
     * 最大允许检测的次数
     * @var int
     */
    protected static $maxCheckNums = 10;

    /**
     * 获取最后一次手机发送的数据
     *
     * @param int $mobile 手机号
     * @param string $event 事件
     * @return  Sms
     */
    public static function get($mobile, $event = 'default')
    {
        $sms = \app\common\model\Sms::
        where(['mobile' => $mobile, 'event' => $event])
            ->order('id', 'DESC')
            ->find();
        Hook::listen('sms_get', $sms, null, true);
        return $sms ? $sms : null;
    }

    /**
     * 获取阿里短信模板
     * getAliTemplate
     * @param string $event
     * @return string
     * author MengShuai <133814250@qq.com>
     * date 2021/01/13 15:57
     */
    private function getAliTemplate(string $event = 'default'): string
    {
        return env("ALIYUN_" . strtoupper($event) . "_TEMPLATE", '');
    }

    /**
     * 发送验证码
     *
     * @param int $mobile 手机号
     * @param int $code 验证码,为空时将自动生成4位数字
     * @param string $event 事件
     * @return  boolean
     */
    public function send($mobile, $code = null, $event = 'default'): bool
    {
        $easySms = ApplicationContext::getContainer()->get(SmsInterface::class);
        $code    = is_null($code) ? mt_rand(1000, 9999) : $code;
        $insert  = [
            'event'      => $event,
            'mobile'     => $mobile,
            'code'       => $code,
            'ip'         => getClientIp(),
            'created_at' => date("Y-m-d H:i:s"),
        ];

        $result = true;
        try {
            $easySms->send($mobile, [
                'template' => $this->getAliTemplate($event),
                'data'     => [
                    'code' => $code,
                ],
            ]);
        } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
            $error  = $exception->getException('aliyun')->getMessage();
            $logger = ApplicationContext::getContainer()->get(LoggerFactory::class)->get('send', 'sms');
            $logger->error($error . "：" . json_encode($insert));
            var_export($error);
            $result = false;
        }
        if (!$result) {
            return false;
        }
        $this->eventDispatcher->dispatch(new SmsEvent($insert, 'send'));
        return true;
    }

    /**
     * 发送通知
     *
     * @param mixed $mobile 手机号,多个以,分隔
     * @param string $msg 消息内容
     * @param string $template 消息模板
     * @return  boolean
     */
    public static function notice($mobile, $msg = '', $template = null)
    {
        $params = [
            'mobile'   => $mobile,
            'msg'      => $msg,
            'template' => $template,
        ];
        $result = Hook::listen('sms_notice', $params, null, true);
        return $result ? true : false;
    }

    /**
     * 校验验证码
     *
     * @param int $mobile 手机号
     * @param int $code 验证码
     * @param string $event 事件
     * @return  boolean
     */
    public function check($mobile, $code, $event = 'default')
    {
        $time = time() - self::$expire;
        $sms  = $this->SmsModel::query()->where(['mobile' => $mobile, 'event' => $event])
            ->orderBy('id', 'desc')
            ->first();

        if ($sms) {
            if (strtotime((string)$sms->created_at) > $time && $sms->times <= self::$maxCheckNums) {
                $correct = $code == $sms->code;
                if (!$correct) {
                    $sms->times = $sms->times + 1;
                    $sms->save();
                    return false;
                } else {
                    return true;
                }
            } else {
                // 过期则清空该手机验证码
                $sms->delete();
                return false;
            }
        } else {
            return false;
        }
    }

}