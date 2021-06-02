<?php
namespace Feishu;

use yii;
use yii\httpclient\Client;

class Message
{
    protected $appid = '';
    protected $appsecret = '';
    private static $client = null;

    public static function getInstance(){
        if(Message::$client === null){
            Message::$client = new client();
        }
        return Message::$client;
    }
    /**
     * 获取配置参数
     * @Author   xmwzg
     * @DateTime 2021-06-01
     * @param    {string}
     * @param    system     $system [项目类型]
     * @return   [type]             [description]
     */
    public function getConfig($system = 'crm_feishu'){
        $params = \Yii::$app->params[$system];
        $this->appid = $params['appid'];
        $this->appsecret = $params['appsecret'];
    }
    /**
     * 获取token
     * @Author   xmwzg
     * @DateTime 2021-06-01
     * @param    {string}
     * @return   [type]     [description]
     */
    public function getToken(){
        $response = Message::getInstance()->createRequest()
            ->setMethod('GET')
            ->setUrl('https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal/')
            ->setData(['app_id' => 'cli_a01126b13ef99013', 'app_secret' => '13Wpi0jDmGaCTf48slTp1b50cWaISslo'])
            ->send();     
        return $response->data;
    
    }
    /**
     * 发送消息  https://open.feishu.cn/open-apis/message/v4/send/
     * @Author   xmwzg
     * @DateTime 2021-06-02
     * @param    {string}
     * @return   [type]     [description]
     */
    public function sendMessage($send_name,$jrs_email,$worker_id){
        if (!YII_ENV_PROD){
            $jrs_email = 'jack.zg.wang@ret.cn';
        }
        $token = $this->getToken();
        $content = [
            'email'=>$jrs_email,
            'msg_type'=>'interactive',
        ];
        $text = [
            'config' => [
                'wide_screen_mode'=>true
            ],
            'card_link'=>[
                "url"=> 'http://crm.ret.cn/worker/index?jsr_workerid='.$worker_id,
                "android_url"=> 'http://crm.ret.cn/worker/index?jsr_workerid='.$worker_id,
                "ios_url"=> 'http://crm.ret.cn/worker/index?jsr_workerid='.$worker_id,
                "pc_url"=> 'http://crm.ret.cn/worker/index?jsr_workerid='.$worker_id
            ],
            'header'=>[
                'title'=>[
                    'tag'=>'plain_text',
                    'content'=>'内部转介通知',
                ]
            ],
            'elements'=>[
                [
                    'tag'=>'div',
                    'text'=>[
                        'tag'=>'plain_text',
                        'content'=>'海豚系统提醒您，'.$send_name.'给您转介了一个项目，请及时跟进处理。',
                    ]
                ],
                [
                    'tag'=>'hr'
                ],
                [
                    "tag"=>"action",
                    "actions"=>[
                        [
                            'tag'=>'button',
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'点击查看详情',
                            ],
                            'url'=>'http://crm.ret.cn/worker/index?jsr_workerid='.$worker_id,
                            'type'=>'default'
                        ]
                    ]

                ]
            ]
        ];
        $content['card'] = $text;
        $response = Message::getInstance()->createRequest()
            ->setMethod('POST')
            ->setUrl('https://open.feishu.cn/open-apis/message/v4/send/')
            ->addHeaders(['content-type' => 'application/json','Authorization'=>'Bearer '.$token['tenant_access_token']])
            ->setContent(json_encode($content))
            ->send();
    }
    /**
     * 发送普通消息
     * @Author   xmwzg
     * @DateTime 2021-06-02
     * @param    {string}
     * @return   [type]     [description]
     */
    public function sendMessageText(){
       
        $token = $this->getToken();
        $content = [
            'receive_id'=>'jack.zg.wang@ret.cn',
            'msg_type'=>'text',
        ];
        $content['content'] = json_encode(['text' => 'two']);
        $response = Message::getInstance()->createRequest()
            ->setMethod('POST')
            ->setUrl('https://open.feishu.cn/open-apis/im/v1/messages?receive_id_type=email')
            ->addHeaders(['content-type' => 'application/json','Authorization'=>'Bearer '.$token['tenant_access_token']])
            ->setContent(json_encode($content))
            ->send();
        echo '<pre>';
        print_r($response->data);die; 
    }

    /**
     * 发送富文本消息
     * @Author   xmwzg
     * @DateTime 2021-06-02
     * @param    {string}
     * @return   [type]     [description]
     */
    public function sendMessageFu(){
        $token = $this->getToken();
        $content = [
            'email'=>'jack.zg.wang@ret.cn',
            'msg_type'=>'post',
        ];
        $text = [
            'post' => [
                'zh_cn' =>[
                    'title' => '转介通知',
                    'content' => [
                        [
                            [
                                'tag' => 'text',
                                'text' => '海豚系统提醒您，某某给您转介了一个项目，请及时跟进处理。',
                            ]
                        ],
                        [ //第二行
                            [
                                'tag' => 'button',
                                'text' => '点击跳转',
                                'href' => 'http://crm.ret.cn',
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $content['content'] = $text;
        $response = Message::getInstance()->createRequest()
            ->setMethod('POST')
            ->setUrl('https://open.feishu.cn/open-apis/message/v4/send/')
            ->addHeaders(['content-type' => 'application/json','Authorization'=>'Bearer '.$token['tenant_access_token']])
            ->setContent(json_encode($content))
            ->send();
        echo '<pre>';
        print_r($response->data);die; 
    }

    /**
     * 发送卡片本消息
     * @Author   xmwzg
     * @DateTime 2021-06-02
     * @param    {string}
     * @return   [type]     [description]
     */
    public function sendMessageCard(){
        $token = $this->getToken();
        $content = [
            'email'=>'jack.zg.wang@ret.cn',
            'msg_type'=>'interactive',
        ];
        $text = [
            'config' => [
                'wide_screen_mode'=>true
            ],
            'header'=>[
                'title'=>[
                    'tag'=>'plain_text',
                    'content'=>'this is header',
                ]
            ],
            'elements'=>[
                [
                    'tag'=>'div',
                    'text'=>[
                        'tag'=>'plain_text',
                        'content'=>'this is very very very very very very long text',
                    ]
                ],
                [
                    'tag'=>'action',
                    'actions'=>[
                        [
                            'tag'=>'button',
                            'text'=>[
                                'tag'=>'plain_text',
                                'content'=>'read',
                            ],
                            'type'=>'default'
                        ]
                    ]
                ]
            ]
        ];
        $content['card'] = $text;
        $response = Message::getInstance()->createRequest()
            ->setMethod('POST')
            ->setUrl('https://open.feishu.cn/open-apis/message/v4/send/')
            ->addHeaders(['content-type' => 'application/json','Authorization'=>'Bearer '.$token['tenant_access_token']])
            ->setContent(json_encode($content))
            ->send();
        echo '<pre>';
        print_r($response->data);die; 
    }

































}