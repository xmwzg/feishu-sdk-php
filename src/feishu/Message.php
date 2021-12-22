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
     *
     * @Author   xmwzg
     * @DateTime 2021-11-08
     * @param    {string}
     * @return   [type]     [description]
     */
    public function getAllUsers(){
        $token = $this->getToken();
        
        $response = Message::getInstance()->createRequest()
            ->setMethod('GET')
            ->setUrl('https://open.feishu.cn/open-apis/contact/v3/departments?department_id_type=open_department_id&fetch_child=true&page_size=50&parent_department_id=0')
            ->addHeaders(['content-type' => 'application/json; charset=utf-8','Authorization'=>'Bearer '.$token['tenant_access_token']])
            ->send();
        $daWang = yii::$app->params['dawang'];

        foreach ($response->data['data']['items'] as $key => $value) {
            $departmentUser = Message::getInstance()->createRequest()
                ->setMethod('GET')
                ->setUrl('https://open.feishu.cn/open-apis/contact/v3/users?department_id='.$value['department_id'].'&department_id_type=department_id')
                ->addHeaders(['content-type' => 'application/json; charset=utf-8','Authorization'=>'Bearer '.$token['tenant_access_token']])
                ->send();
            if (isset($departmentUser->data['data']['items'])) {
                foreach ($departmentUser->data['data']['items'] as $k => $v) {
                    // echo '<pre>';
                    // print_r($v);die;
                    $user['username'][] =  $v['name'];
                    $user['avatar'][] =  $v['avatar']['avatar_72'];
                    $user['feid'][] =  $v['user_id'];
                    $user['email'][] =  $v['email'];
                    $user['company'][] =  $value['name'];
                }
            }    
        }
        foreach ($user['username'] as $key => $value) {
            if(in_array($value, $daWang)){
                continue;
            }
           $newUser[$key]['username'] = $value;
           $newUser[$key]['created_at'] = strtotime(date('Y-m-d'));
           $newUser[$key]['company'] = $user['company'][$key];
           $newUser[$key]['avatar'] = $user['avatar'][$key];
           $newUser[$key]['feid'] = $user['feid'][$key];
           $newUser[$key]['email'] = $user['email'][$key];
        }
        return $newUser;
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
     * 获取部门信息
     * @Author   xmwzg
     * @DateTime 2021-06-16
     * @param    {string}
     * @return   [type]     [description]
     */
    public function getDepartInfo(){
        $token = $this->getToken();
        $response = Message::getInstance()->createRequest()
            ->setMethod('GET')
            ->setUrl('https://open.feishu.cn/open-apis/im/v1/chats')
            ->addHeaders(['content-type' => 'application/json; charset=utf-8','Authorization'=>'Bearer '.$token['tenant_access_token']])
            ->send();
        echo '<pre>';
        print_r($response);die;   
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
     * 飞书同步请假审批状态
     * @Author   xmwzg
     * @DateTime 2021-08-12
     * @param    {listenData 飞书审批完成后监听数据}
     * @return   [type]     [description]
     */
    public function syncApproveStatus($listenData){
        $listenData = json_decode($listenData,true);
        $type = $listenData['event']['type'];
        //leave_approvalV2飞书新版请假消息
        if($type == 'leave_approvalV2'){
            $calendarData = [
                'user_id'=>$listenData['event']['user_id'],
                'timezone'=>'Asia/Shanghai',
                'start_time'=>strtotime($listenData['event']['leave_start_time']),
                'end_time'=>strtotime($listenData['event']['leave_end_time']),
                'title'=> '请假中',
                'description'=> $listenData['event']['leave_reason']
            ];

            $token = $this->getToken();
            $response = Message::getInstance()->createRequest()
                ->setMethod('POST')
                ->setUrl('https://open.feishu.cn/open-apis/calendar/v4/timeoff_events')
                ->addHeaders(['content-type' => 'application/json','Authorization'=>'Bearer '.$token['tenant_access_token']])
                ->setContent(json_encode($calendarData))
                ->send();
            $result = $response->data;
            if($result['code'] === 0){
                 header("HTTP/1.1 200 OK");
            }
        }

    }
    /**
     * 发送消息  https://open.feishu.cn/open-apis/message/v4/send/
     * @Author   xmwzg
     * @DateTime 2021-06-02 海豚系统提醒您，'.$send_name.'给您转介了一个项目，工单ID为 '.$worker_id.'，请及时跟进处理。
     * @param    {$email array 接收人email}
     * @return   [type]     [description]
     */
    public function sendMessage($contentText='',$email=[],$link=''){
        $emailResult = $this->getUsersInfo($email);
        $sendUser = [];
        if($emailResult){
            foreach ($emailResult as $key => $value) {
                $sendUser[] = $value[0]['user_id'];
            }
        }
        $token = $this->getToken();

        $headerContent = '海豚通知';

        if (!YII_ENV_PROD){
            $sendUser = [];
            $sendUser = ['355371e4'];
            $headerContent = '测试海豚通知';
        }
        $content = [
            'user_ids'=> $sendUser,
            'msg_type'=>'interactive',
        ];
        $text = [
            'config' => [
                'wide_screen_mode'=>true
            ],
            'card_link'=>[
                "url"=> $link,
                "android_url"=> $link,
                "ios_url"=> $link,
                "pc_url"=> $link
            ],
            'header'=>[
                'title'=>[
                    'tag'=>'plain_text',
                    'content'=>$headerContent,
                ]
            ],
            'elements'=>[
                [
                    'tag'=>'div',
                    'text'=>[
                        'tag'=>'plain_text',
                        'content'=>$contentText,
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
                            'url'=>$link,
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
    public function sendMessageFu($data){
        $token = $this->getToken();
        $content = [
            'chat_id'=> 'oc_00e07d4406f5b7bb3309e6adf53ebb00',
            'msg_type'=>'interactive',
        ];
        $text = [
            'config' => [
                'wide_screen_mode'=>true
            ],
            'header'=>[
                'title'=>[
                    'tag'=>'plain_text',
                    'content'=>'微信线索',
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
                                'content'=>"**     城市**\n       ".$data['chengshi']
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**     姓名**\n       ".$data['xingming']
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**     手机**\n       ".$data['shouji']
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**     邮箱**\n       ".$data['youxiang']
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**     gongsi**\n       ".$data['gongsi']
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**     来源**\n       ".$data['source']
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**     留言**\n       ".$data['liuyan']
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
                            'is_short'=>false,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'',
                            ]
                        ],
                    ]
                ],
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
     * 发送卡片本消息
     * @Author   xmwzg
     * @DateTime 2021-06-02
     * @param    {string}
     * @return   [type]     [description]
     */
    public function sendMessageCard($uid){
        $token = $this->getToken();
        $test = '';
        if (!YII_ENV_PROD){
            $uid = '355371e4';
            $test = 'ceshi';
        }
        $content = [
            'user_id'=> $uid,
            'msg_type'=>'interactive',
        ];
        $text = [
            'config' => [
                'wide_screen_mode'=>true
            ],
            'header'=>[
                'title'=>[
                    'tag'=>'plain_text',
                    'content'=>'请及时提问哦~',
                ],
                'template'=>'#ca151c'
            ],
            'elements'=>[
                [
                    'tag'=>'div',
                    'text'=>[
                        'tag'=>'plain_text',
                        'content'=>'提问时间: 每日 09:00-21:00',
                    ]
                ],
                [
                    'tag'=>'action',
                    'actions'=>[
                        [
                            'tag'=>'button',
                            'text'=>[
                                'tag'=>'plain_text',
                                'content'=>'现在就去提'.$test,
                            ],
                            'type'=>'primary',
                            'url'=>"https://applink.feishu.cn/TRwF2MgP"
                        ],
                        [
                            'tag'=>'button',
                            'text'=>[
                                'tag'=>'plain_text',
                                'content'=>'精彩问答传送门',
                            ],
                            'type'=>'primary',
                            'url'=>"https://applink.feishu.cn/client/web_app/open?appId=cli_a1f347b22538500d&mode=appCenter&url=https://open.feishu.cn/open-apis/authen/v1/index?app_id=cli_a1f347b22538500d&redirect_uri=http%3A%2F%2Fqa.ret.cn%2Fsite%2Fflogin"
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
    public function sendWorker($title,$all_worker,$channel,$img,$sendAdd,$project){
        if(is_array($sendAdd)){
            //获取用户飞书user_id
            $users_id = $this->getUsersInfo($sendAdd);
            if(empty($users_id)){
                return false;
            }
            $send_user_ids = [];

            foreach ($users_id as $key => $value) {
                $send_user_ids[] = $value[0]['user_id'];
            }
            $content = [];
            $content = [
                'user_ids'=> $send_user_ids,
                'msg_type'=>'interactive',
            ];
            $sendUrl = 'https://open.feishu.cn/open-apis/message/v4/batch_send/';

        }else{
            $content = [];
            $content = [
                'chat_id'=> $sendAdd,
                'msg_type'=>'interactive',
            ];
            $sendUrl = 'https://open.feishu.cn/open-apis/message/v4/send/';
        }

        if (!YII_ENV_PROD){
            $content = [];
            $content = [
                'user_ids'=> ['355371e4'],
                'msg_type'=>'interactive',
            ];
            $sendUrl = 'https://open.feishu.cn/open-apis/message/v4/batch_send/';
        }

        $token = $this->getToken();
        $wj = isset($channel[2])?$channel[2]:0;
        $yj = isset($channel[1])?$channel[1]:0;
        $zj = isset($channel[4])?$channel[4]:0;


        $text = [
            'config' => [
                'wide_screen_mode'=>true
            ],
            'header'=>[
                'title'=>[
                    'tag'=>'plain_text',
                    'content'=>$title.'-待处理工单线索统计',
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
                                'content'=>"**     未处理工单数**\n       ".count($all_worker)
                            ]
                        ],
                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**     逾期未推进线索数**\n       ".count($project)
                            ]
                        ],
                        [
                            'is_short'=>false,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'',
                            ]
                        ],
                        // [
                        //     'is_short'=>true,
                        //     'text'=>[
                        //         'tag'=>'lark_md',
                        //         'content'=>"**     400未接**\n      ".$wj
                        //     ]
                        // ],

                        // [
                        //     'is_short'=>true,
                        //     'text'=>[
                        //         'tag'=>'lark_md',
                        //         'content'=>"**     400已接**\n       ".$yj
                        //     ]
                        // ],
                        [
                            'is_short'=>false,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>'',
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
                                'content'=>'查看线索详情',
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
              ->setUrl($sendUrl)
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

    /**
     * 财务会快账单
     * @Author   xmwzg
     * @DateTime 2021-06-30
     * @param    {imgArr 项目名称}
     * @param    {chatId 群ID}
     * @param    {projectName 项目名称}
     * @param    {collectionMoney 收款金额}
     * @param    {moneyType 款项期次}
     * @return   [type]     [description]
     */
    public function sendReturnBill($imgArr,$chatId,$projectName,$collectionMoney,$moneyType){
        $token = $this->getToken();
        $img_str = '';
        foreach ($imgArr as $key => $value) {
            $img_str .= "[附件".($key+1)."](http://ret-crm.oss-cn-beijing.aliyuncs.com/".$value."),";
        }
        if (!YII_ENV_PROD){
            $chatId = 'oc_78ce5b9dc267f972e80adae1f3833e64';
        }
        $content = [
            'chat_id'=> $chatId,
            'msg_type'=>'interactive',
        ];
        $text = [
            'config' => [
                'wide_screen_mode'=>true
            ],
            'header'=>[
                'title'=>[
                    'tag'=>'plain_text',
                    'content'=> $projectName . ' 回款账单通知',
                ],
                'template'=>'red'
            ],
            'elements'=>[
                [
                    'tag'=>'div',
                    'text'=>[
                        'tag'=>'lark_md',
                        // 'content'=>"[飞书](https://www.feishu.cn)整合即时沟通、日历、音视频会议、云文档、云盘、工作台等功能于一体，成就组织和个人，更高效、更愉悦。"
                        'content'=>"海豚系统提醒您，当前有回款账单已上传，点击".trim($img_str,',')."查看详情"
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
                                'content'=>"**收款金额**\n".$collectionMoney.'(W)'
                            ]
                        ],

                        [
                            'is_short'=>true,
                            'text'=>[
                                'tag'=>'lark_md',
                                'content'=>"**款项期次**\n".$moneyType
                            ]
                        ],
                    ],
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

























}