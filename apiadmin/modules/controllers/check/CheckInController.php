<?php
namespace apiadmin\modules\controllers\check;
use apiadmin\modules\controllers\CoreController;
use apiadmin\modules\models\check\CheckIn;
use common\models\member\MemberModel;
use common\models\room\RoomModel;
use common\models\room\RoomStateModel;

/**
 * 入住相关控制器
 */

class CheckInController extends CoreController
{
    /*
        *入住列表
    */
    public function actionCheckInList()
    {
        $where  = $this->formartWhere();
        $params = array(
            'field'	=> ['check_in_id','m.member_name','sm.member_name second_member_name','room_name','type_name','ci.deposit','ci.charge','ci.mark','in_time','out_time'],
            'order' => 'check_in_id desc',
            'page'	=> $this->request('page','1'),
            'limit' => $this->request('page_size',10),
        );
        $list = CheckIn::CheckInList($where,$params);
        $pages = CheckIn::$pages;
        $this->out('入住列表',$list,array('pages'=>$pages));
    }

    //获取未入住下拉数据
    public function actionGetListAll()
    {
        $params = $this->request;
        $roomId = isset($params['room_id']) ? $params['room_id'] : 0;
        $list['room'] = RoomModel::getNullRoom($roomId);

        $memberId = isset($params['member_id']) ? $params['member_id'] : 0;
        $list['member'] = MemberModel::getMemberAll($memberId);
        $this->out('会员入住',$list);
    }

    //组装条件
    public function formartWhere()
    {
        $where = [];
        $whereAnd = [];
        $searchKeys = json_decode($this->request('search'),1);
        if(!$searchKeys) return array('where'=>$where,'whereAnd'=>$whereAnd);
        foreach($searchKeys as $k=>$val)
        {
            if(!$val) continue;
            if($k=='inDate' || $k=='outDate')
            {
                if(!$val['0'] || !$val['1']) continue;
                $date = $k=='inDate' ? 'in_time' :'out_time';
                $whereAnd[] = ['between', $date, strtotime($val[0]),strtotime($val[1])];
            }elseif ($k=='member_name' || $k=='room_name') {
                $whereAnd[] = ['like',$k,$val];
            }else
            {
                if ($k=='type_id') {
                    $k = 'r.type_id';
                }
                $where[$k] = $val;
            }
        }

        return array('where'=>$where,'whereAnd'=>$whereAnd);
    }

    /*
        删除入住
        CheckIn_id
    */
    public function actionCheck_in_del()
    {
        if(!$CheckInId = $this->request('check_in_id')) $this->error('参数错误');
        $checkInfo = CheckIn::getInfo(['check_in_id' => $CheckInId]);
        $checkInfo['charge'] = $this->request('charge');
        $checkInfo['mark'] = $this->request('mark');
        $res = CheckIn::CheckInDel($CheckInId);
        if($res){
            //修改用户和房间状态
            MemberModel::setStateId($checkInfo['member_id'],RoomStateModel::getRoomStateId(),$checkInfo['charge']);
            if (!empty($checkInfo['second_member_id'])) {
                MemberModel::setStateId($checkInfo['second_member_id'],RoomStateModel::getRoomStateId());
            }
            RoomModel::setStateId($checkInfo['room_id'],RoomStateModel::getRoomStateId('清扫中'));
            //添加退房信息
            $checkOut = $this->model('check\CheckOut',$checkInfo,'Reg');
            $checkOut->user_id = $this->_uid;
            $checkOut->out_time = time();
            $checkOut->save();
            $this->out('退房成功');
        }
        $this->error('退房失败');
    }

    /*
        获取单入住信息
        * CheckIn_id 入住ID
    */
    public function actionCheck_in()
    {
        if(!$CheckInId = $this->request('check_in_id')) $this->error('参数错误');
        $field = ['ci.*','member_card_id','room_name','member_name','type_name','price','discount'];
        $CheckIn = CheckIn::getCheckIdById($CheckInId,$field);
        $this->out('入住信息',$CheckIn);
    }

    //入住信息修改
    public function actionCheck_in_edit()
    {
        $params = $this->request;
        $params['user_id'] = $this->_uid;
        $checkIn = $this->model('check\CheckIn',$params,'Edit',$params['check_in_id']);
        if(!$checkIn->save(false)) $this->error('修改失败');
        $this->out('修改成功');
    }

    //入住添加
    public function actionCheck_in_add()
    {
        $params = $this->request;
        $params['user_id'] = $this->_uid;
        //游客入住。先添加会员
        if (isset($params['member_name'])) {
            if (!empty($params['second_member_name'])) {
                $data = [
                    'member_name' => $params['second_member_name'],
                    'member_card_id' => $params['second_member_card_id'],
                    'sex' => substr($params['second_member_card_id'],-2,1),
                    'state_id' => RoomStateModel::getRoomStateId('已入住'),
                ];
                $secondMemberModel = $this->model('member\Member',$data,'Reg');
                $secondMemberModel->save();
                $params['second_member_id'] = $secondMemberModel->getPrimaryKey();
            }
            $memberModel = $this->model('member\Member',$params,'Reg');
            $memberModel->sex = substr($params['member_card_id'],-2,1);
            $memberModel->state_id = RoomStateModel::getRoomStateId('已入住');
            $memberModel->save();
            $params['member_id'] = $memberModel->getPrimaryKey();
        }

        $checkIn = $this->model('check\CheckIn',$params,'Reg');
        $checkIn->charge = bcadd(bcmul($params['price'],$params['discount'],2),$params['deposit'],2);
        if(!$checkIn->save(false)) $this->error('添加失败');
        //修改用户和房间状态
        MemberModel::setStateId($params['member_id'],RoomStateModel::getRoomStateId('已入住'));
        if (isset($params['second_member_id'])) {
            MemberModel::setStateId($params['second_member_id'],RoomStateModel::getRoomStateId('已入住'));
        }
        RoomModel::setStateId($params['room_id'],RoomStateModel::getRoomStateId('已入住'));
        //返回数据
        $this->out('添加成功');
    }

    //会员导出
    public function actionExport()
    {
        /*$where  = $this->formartWhere();
        $params = array(
            'field'	=> ['member_id','member_name','member_mobile','invite_id','state',
                'create_time','update_time'],
            'order' => 'member_id desc',
            'page'	=> $this->request('page','1'),
            'limit' => $this->request('page_size',10),
        );
        $list = Member::MemberList($where,$params);

        //组织导出数据
        $exportData = array();
        foreach($list as $val)
        {

            $temp = [];
            $temp[] = $val['member_id'];
            $temp[] = $val['member_name'];
            $temp[] = $val['member_mobile'];
            $temp[] = $val['state']?'正常':'冻结';
            $temp[] = date("Y-m-d H:i:s",$val['create_time']);
            $exportData[] = $temp;
        }
        $headData = array('A1'=>'会员ID','B1'=>'会员姓名','C1'=>'会员电话','D1'=>'会员状态','E1'=>'注册时间');
        $fileName = 'member-'.date('Y-m-d').'.xls';
        $execlObj = new OutputExecl();
        $res = $execlObj->output($headData,$exportData,$fileName);
        if($res)
            $this->out('下载地址',array('url'=>$res));
        else
            $this->error('导出失败');*/
    }
}