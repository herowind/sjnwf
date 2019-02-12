<?php

// +----------------------------------------------------------------------
// | framework
// +----------------------------------------------------------------------
// | 版权所有 2014~2018 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://framework.thinkadmin.top
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/framework
// +----------------------------------------------------------------------

namespace app\service\controller;

use app\service\logic\Build;
use app\service\logic\Wechat;
use library\Controller;
use think\Db;

/**
 * Class Index
 * @package app\service\controller
 */
class Index extends Controller
{

    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'WechatServiceConfig';

    /**
     * 微信基础参数配置
     * @return string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '微信授权管理';
        return $this->_query($this->table)
            ->like('authorizer_appid,nick_name,principal_name')
            ->equal('service_type,status')->dateBetween('create_at')
            ->where(['is_deleted' => '0'])->order('id desc')->page();

    }

    /**
     * 同步获取权限
     */
    public function sync()
    {
        try {
            $appid = $this->request->get('appid');
            $where = ['authorizer_appid' => $appid, 'is_deleted' => '0', 'status' => '1'];
            $author = Db::name('WechatServiceConfig')->where($where)->find();
            empty($author) && $this->error('无效的授权信息，请同步其它公众号！');
            $info = Build::filter(Wechat::service()->getAuthorizerInfo($appid));
            $info['authorizer_appid'] = $appid;
            if (data_save('WechatServiceConfig', $info, 'authorizer_appid')) {
                $this->success('更新公众号授权信息成功！', '');
            }
        } catch (\think\exception\HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $e) {
            $this->error("获取授权信息失败，请稍候再试！<br>{$e->getMessage()}");
        }
    }

    /**
     * 同步获取所有授权公众号记录
     */
    public function syncall()
    {
        try {
            $wechat = Wechat::service();
            $result = $wechat->getAuthorizerList();
            foreach ($result['list'] as $item) if (!empty($item['refresh_token']) && !empty($item['auth_time'])) {
                $data = Build::filter($wechat->getAuthorizerInfo($item['authorizer_appid']));
                $data['authorizer_appid'] = $item['authorizer_appid'];
                $data['authorizer_refresh_token'] = $item['refresh_token'];
                $data['create_at'] = date('Y-m-d H:i:s', $item['auth_time']);
                if (!data_save('WechatServiceConfig', $data, 'authorizer_appid')) {
                    $this->error('获取授权信息失败，请稍候再试！', '');
                }
            }
            $this->success('同步所有授权信息成功！', '');
        } catch (\think\exception\HttpResponseException $exception) {
            throw $exception;
        } catch (\Exception $e) {
            $this->error("同步授权失败，请稍候再试！<br>{$e->getMessage()}");
        }
    }

    /**
     * 删除微信
     */
    public function del()
    {
        $this->_delete($this->table);
    }

    /**
     * 微信禁用
     */
    public function forbid()
    {
        $this->_save($this->table, ['status' => '0']);
    }

    /**
     * 微信启用
     */
    public function resume()
    {
        $this->_save($this->table, ['status' => '1']);
    }
}