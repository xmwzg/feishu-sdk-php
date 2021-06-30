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
        $this->getConfig();
        $response = Message::getInstance()->createRequest()
            ->setMethod('GET')
            ->setUrl('https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal/')
            ->setData(['app_id' => $this->appid, 'app_secret' => $this->appsecret])
            ->send(); 
        return $response->data;
    
    }
    /**
     * 根据邮箱获取用户信息 弃用
     * @Author   xmwzg
     * @DateTime 2021-06-16
     * @param    {string}
     * @return   [type]     [description]
     */
    public function getUserInfo($email){
        $token = $this->getToken();
        $response = Message::getInstance()->createRequest()
            ->setMethod('GET')
            ->setUrl('https://open.feishu.cn/open-apis/user/v1/batch_get_id?emails='.$email)
            ->addHeaders(['content-type' => 'application/json; charset=utf-8','Authorization'=>'Bearer '.$token['tenant_access_token']])
            ->send();
        if(isset($response->data['data']['email_users'])){
            return $response->data['data']['email_users'][$email][0];
        }    
        return false; 
    }
    /**
     * 根据邮箱批量获取用户信息
     * @Author   xmwzg
     * @DateTime 2021-06-16
     * @param    {string}
     * @return   [type]     [description]
     */
    public function getUsersInfo($email){
        $token = $this->getToken();
        $params_str = implode('&emails=',$email);

        $response = Message::getInstance()->createRequest()
            ->setMethod('GET')
            ->setUrl('https://open.feishu.cn/open-apis/user/v1/batch_get_id?emails='.$params_str)
            ->addHeaders(['content-type' => 'application/json; charset=utf-8','Authorization'=>'Bearer '.$token['tenant_access_token']])
            ->send();
        if(isset($response->data['data']['email_users'])){
            return $response->data['data']['email_users'];
        }    
        return false; 
    }
    /**
     * 获取飞书用户信息
     * @Author   xmwzg
     * @DateTime 2021-06-03
     * @param    {string}
     * @return   [type]     [description]
     */
    public function getLoginUser($code){
        $token = $this->getToken();
        $response = Message::getInstance()->createRequest()
            ->setMethod('POST')
            ->setUrl('https://open.feishu.cn/open-apis/authen/v1/access_token')
            ->addHeaders(['content-type' => 'application/json','Authorization'=>'Bearer '.$token['tenant_access_token']])
            ->setContent(json_encode(['grant_type' => 'authorization_code', 'code' => $code]))
            ->send();
        return $response->data;   
    }
    /**
     * 发送消息  https://open.feishu.cn/open-apis/message/v4/send/
     * @Author   xmwzg
     * @DateTime 2021-06-02
     * @param    {$jsr_email 接收人email}
     * @return   [type]     [description]
     */
    public function sendMessage($send_name,$jsr_email,$link_url,$worker_id){

        $user_ids = $this->getUserInfo($jsr_email);
        $token = $this->getToken();

        if($user_ids){
            $send_user_ids = ['355371e4',$user_ids['user_id']];
            $header_content = '内部转介通知';
        }else{
            $send_user_ids = ['355371e4'];
            $header_content = '内部转介通知(接收人无)';
        }
        if (!YII_ENV_PROD){
            $send_user_ids = [];
            $send_user_ids = ['355371e4'];
            $header_content = '测试内部转介通知';
        }
        $content = [
            'user_ids'=> $send_user_ids,
            'msg_type'=>'interactive',
        ];
        $text = [
            'config' => [
                'wide_screen_mode'=>true
            ],
            'card_link'=>[
                "url"=> $link_url,
                "android_url"=> $link_url,
                "ios_url"=> $link_url,
                "pc_url"=> $link_url
            ],
            'header'=>[
                'title'=>[
                    'tag'=>'plain_text',
                    'content'=>$header_content,
                ]
            ],
            'elements'=>[
                [
                    'tag'=>'div',
                    'text'=>[
                        'tag'=>'plain_text',
                        'content'=>'海豚系统提醒您，'.$send_name.'给您转介了一个项目，工单ID为 '.$worker_id.'，请及时跟进处理。',
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
                            'url'=>$link_url,
                            'type'=>'default'
                        ]
                    ]

                ]
            ]
        ];
        $content['card'] = $text;
        $response = Message::getInstance()->createRequest()
            ->setMethod('POST')
            ->setUrl('https://open.feishu.cn/open-apis/message/v4/batch_send/')
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
    /**
     * 上传图片
     * @Author   xmwzg
     * @DateTime 2021-06-22
     * @param    {string}
     */
    public function UploadImg($image){
        $token = $this->getToken();

        $response = Message::getInstance()->createRequest()
            ->setMethod('POST')
            ->setUrl('https://open.feishu.cn/open-apis/im/v1/images')
            ->addHeaders(['content-type' => 'multipart/form-data; boundary=---7MA4YWxkTrZu0gW','Authorization'=>'Bearer '.$token['tenant_access_token']])
            ->setData(['image_type' => 'message', 'image' => $image])
            ->send();
        return $response->data;
    }
    /**
     * 400未处理工单
     * @Author   xmwzg
     * @DateTime 2021-06-22
     * @param    {string}
     */
    public function SendWorker($title,$all_worker,$channel,$img,$send_email,$project){
        //获取用户飞书user_id
        $users_id = $this->getUsersInfo($send_email);
        $send_user_ids = [];
        if($users_id){
            foreach ($users_id as $key => $value) {
                $send_user_ids[] = $value[0]['user_id'];
            }
        }

        if(empty($send_user_ids)){
            $send_user_ids = ['355371e4'];
        }

        if (!YII_ENV_PROD){
            $send_user_ids = [];
            $send_user_ids = ['355371e4'];
        }
        $token = $this->getToken();
        $wj = isset($channel[2])?$channel[2]:0;
        $yj = isset($channel[1])?$channel[1]:0;
        $zj = isset($channel[4])?$channel[4]:0;
        $content = [
            'user_ids'=> $send_user_ids,
            'msg_type'=>'interactive',
        ];
        $text = [
            'config' => [
                'wide_screen_mode'=>true
            ],
            'header'=>[
                'title'=>[
                    'tag'=>'plain_text',
                    'content'=>$title.'-400未处理工单、逾期项目未处理提醒',
                ],
                'template'=>'#ca151c'
            ],
            'elements'=>[
                [
                    'tag'=>'div',
                    'text'=>[
                        'tag'=>'lark_md',
                        'content'=>"",
                    ],
                    'fields'=>[
                        [
                            'is_short'=>false,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'',
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**     工单未处理总数**\n       ".count($all_worker)
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**     项目未处理总数**\n       ".count($project)
                            ]
                        ],
                        [
                            'is_short'=>false,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'',
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**     400未接**\n      ".$wj
                            ]
                        ],

                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**     400已接**\n       ".$yj
                            ]
                        ],
                        [
                            'is_short'=>false,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'',
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**     内部转介**\n       ".$zj
                            ]
                        ],
                    ]
                ],
                [
                    'tag'=>'img',
                    'img_key'=>$img ? $img : '',
                    'alt'=>[
                        'tag'=>'plain_text',
                        'content'=>'图片',
                    ]
                ],
                [
                    "tag"=>"action",
                    "actions"=>[
                        [
                            'tag'=>'button',
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'查看工单详情',
                            ],
                            'url'=>'https://applink.feishu.cn/client/web_app/open?appId=cli_a01126b13ef99013&mode=appCenter&url=http://crm.ret.cn/worker/index?from=1&state=http://crm.ret.cn/worker/index?from=1',
                            'type'=>'default'
                        ],
                        [
                            'tag'=>'button',
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'查看项目详情',
                            ],
                            'url'=>'https://applink.feishu.cn/client/web_app/open?appId=cli_a01126b13ef99013&mode=appCenter&url=http://crm.ret.cn/project/today-commun&state=http://crm.ret.cn/project/today-commun',
                            'type'=>'default'
                        ]
                    ]

                ]
            ]
        ];
        $content['card'] = $text;
        try {
          $response = Message::getInstance()->createRequest()
              ->setMethod('POST')
              ->setUrl('https://open.feishu.cn/open-apis/message/v4/batch_send/')
              ->addHeaders(['content-type' => 'application/json','Authorization'=>'Bearer '.$token['tenant_access_token']])
              ->setContent(json_encode($content))
              ->send();
        } catch (\Exception $e) {
        }

    }
    /**
     * 应聘申请
     * @Author   xmwzg
     * @DateTime 2021-06-24
     * @param    {string}
     * @param    [type]     $data       [description]
     * @param    [type]     $send_email [description]
     * @return   [type]                 [description]
     */
    public function sendJob($data,$send_email){
        //获取用户飞书user_id
        $users_id = $this->getUsersInfo($send_email);
        $send_user_ids = [];
        foreach ($users_id as $key => $value) {
            $send_user_ids[] = $value[0]['user_id'];
        }
        if (!YII_ENV_PROD){
            $send_user_ids = [];
            $send_user_ids = ['355371e4'];
        }

        $token = $this->getToken();
        $content_text =  $data['file_url'] ? "**简历**\n"."[".$data['file_name']."]"."("."http://retwebsite.oss-cn-beijing.aliyuncs.com/".$data['file_url'].")" : "**简历**\n无简历";
        $content = [
            'user_ids'=> $send_user_ids,
            'msg_type'=>'interactive',
        ];
        $text = [
            'config' => [
                'wide_screen_mode'=>true
            ],
            'header'=>[
                'title'=>[
                    'tag'=>'plain_text',
                    'content'=>'应聘申请通知',
                ],
                'template'=>'red'
            ],
            'elements'=>[
                [
                    'tag'=>'div',
                    'text'=>[
                        'tag'=>'lark_md',
                        'content'=>"",
                    ],
                    'fields'=>[
                        [
                            'is_short'=>false,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'',
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**姓名**\n".$data['username']
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**性别**\n".$data['sex']
                            ]
                        ],
                        [
                            'is_short'=>false,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'',
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**手机号**\n".$data['mobile']
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**最高学历**\n".$data['h_school']
                            ]
                        ],
                        [
                            'is_short'=>false,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'',
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**毕业院校**\n".$data['school']
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**专业**\n".$data['major']
                            ]
                        ],
                        [
                            'is_short'=>false,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'',
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**毕业时间**\n".$data['graduation_time']
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**参加工作时间**\n".$data['job_time']
                            ]
                        ],
                        [
                            'is_short'=>false,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'',
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**申请类型**\n".$data['type']
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**应聘部门**\n".$data['area']
                            ]
                        ],
                        [
                            'is_short'=>false,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'',
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**申请岗位**\n".$data['post'].'-'.$data['sqgw_name']
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=> $content_text
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $content['card'] = $text;
        $response = Message::getInstance()->createRequest()
            ->setMethod('POST')
            ->setUrl('https://open.feishu.cn/open-apis/message/v4/batch_send/')
            ->addHeaders(['content-type' => 'application/json','Authorization'=>'Bearer '.$token['tenant_access_token']])
            ->setContent(json_encode($content))
            ->send();
    }





























}