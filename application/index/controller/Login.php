<?php
/**
 * Created by PhpStorm.
 * User: a3625
 * Date: 2018/1/28
 * Time: 18:52
 */
namespace app\index\controller;
use app\UserBaseController;
use think\Validate;
use think\Db;
class Login extends UserBaseController
{
    public function index(){
        //导航栏
        $nav = $this->navList();
        $this->assign('nav',$nav);

        return $this->fetch();
    }
    //用户登录提交
    public function doLogin(){
        $data = $this->request->param();
        $username = $data['username'];
        $password = md5($data['password']);
        $captcha = $data['captcha'];


        if (!cmf_captcha_check($captcha)){
            $this->error('验证码错误！',url('index/Login/index'),'用户登录');
        }
        $result = Db::name('staff')->where(['username'=>$username,'delete_time'=>0])->find();
        if ($result){
            if ($result['status']==0) $this->error('该帐号被限制登录！',url('index/Login/index'),'用户登录');
            if ($result['username']==$username&&$result['password']==$password) {
                //将登录用户信息写进session
                session('user', $result);
                //更新用户最后登录时间,online字段置1
                Db::name('staff')->where('id',$result['id'])->update(['last_login_time'=>time(),'online'=>1]);

                $this->success('登录成功！', url('index/index'),'用户登录');
            }else{
                $this->error('用户名密码错误！');
            }
        }else{
            $this->error('不存在此用户！');
        }
    }
    //用户注销
    public function loginOut(){

        $this->successLoginout('注销成功！',url('index/index/index'),'用户注销');

    }

    //忘记密码 重置界面
    public function resetPwd(){
        $nav = $this->navList();
        $this->assign('nav',$nav);
        return $this->fetch();
    }
    //密码重置提交
    public function resetPwdPost(){
        $data = $this->request->param();
        $emailCode = $data['emailCode'];
        $username = $data['username'];
        $newPassword = md5($data['newPassword']);

        if($emailCode == session('emailCode')){
            //清空session
            session('emailCode',null);
            session('resetUserId',null);
            //重置staff表的用户密码
            $result = Db::name('staff')->where(['delete_time'=>0,'username'=>$username])->update(['password'=>$newPassword]);
            if ($result){
                $this->success('密码重置成功！',url('index/Login/index'),'密码重置');
            }else{
                $this->error('密码重置失败！请重试');
            }
        }else{
            $this->error('邮箱验证码错误！');
        }
    }
    //发送邮箱验证码，接收ajax传递的email
    public function sendCode($userEmail){
        //随机产生4位验证码
        $code = rand(1000,9999);
        //将验证码保存进session
        session('emailCode',$code);
        //发送验证码，返回1或0
        $send = sendEmail($userEmail, $code);
        //根据邮箱查找用户id
        $user = Db::name('staff')->where(['delete_time'=>0,'email'=>$userEmail])->find();
        $userId = $user['id'];
        //将userid写入session，使其可以在未登录的情况下记录充值密码操作日志
        session('resetUserId',$userId);
        //将操作记录写入数据库
        $data = [
            'code'=>$code,
            'email'=>$userEmail,
            'staff_id'=>$userId,
            'create_time'=>time()
        ];
        Db::name('email_code')->insert($data);

        return json([
            "status" => $send['status'],
        ]);
    }
}