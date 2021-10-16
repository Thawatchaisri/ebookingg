<?php
/**
 * @filesource modules/car/models/order.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Car\Order;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=car-order
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลรายการที่เลือก
     * คืนค่าข้อมูล object ไม่พบคืนค่า null
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        $query = static::createQuery()
            ->from('car_reservation V')
            ->join('user U', 'LEFT', array('U.id', 'V.member_id'))
            ->where(array('V.id', $id));
        $select = array('V.*', 'U.name', 'U.phone', 'U.username');
        $n = 1;
        foreach (Language::get('CAR_OPTIONS', array()) as $key => $label) {
            $query->join('car_reservation_data M'.$n, 'LEFT', array(array('M'.$n.'.reservation_id', 'V.id'), array('M'.$n.'.name', $key)));
            $select[] = 'M'.$n.'.value '.$key;
            ++$n;
        }
        return $query->first($select);
    }

    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม (order.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = array();
        // session, token, สามารถอนุมัติได้
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_approve_car')) {
                try {
                    // ค่าที่ส่งมา
                    $save = array(
                        'vehicle_id' => $request->post('vehicle_id')->toInt(),
                        'travelers' => $request->post('travelers')->toInt(),
                        'detail' => $request->post('detail')->textarea(),
                        'comment' => $request->post('comment')->textarea(),
                        'chauffeur' => $request->post('chauffeur')->toInt(),
                        'begin' => $request->post('begin')->date(),
                        'end' => $request->post('end')->date(),
                        'status' => $request->post('status')->toInt(),
                        'reason' => $request->post('reason')->topic(),
                    );
                    $datas = array();
                    foreach (Language::get('CAR_OPTIONS', array()) as $key => $label) {
                        $values = $request->post($key, array())->toInt();
                        if (!empty($values)) {
                            $datas[$key] = implode(',', $values);
                        }
                    }
                    // ตรวจสอบรายการที่เลือก
                    $index = self::get($request->post('id')->toInt());
                    if ($index) {
                        if ($save['detail'] == '') {
                            // ไม่ได้กรอก detail
                            $ret['ret_detail'] = 'Please fill in';
                        }
                        if (empty($save['begin'])) {
                            // ไม่ได้กรอก begin
                            $ret['ret_begin'] = 'Please fill in';
                        } else {
                            $save['begin'] .= ':01';
                        }
                        if (empty($save['end'])) {
                            // ไม่ได้กรอก end
                            $ret['ret_end'] = 'Please fill in';
                        } else {
                            $save['end'] .= ':00';
                        }
                        if ($save['end'] > $save['begin']) {
                            // ตรวจสอบว่าง เฉพาะรายการที่จะอนุมัติ
                            if ($save['status'] == 1 && !\Car\Checker\Model::availability($save, $index->id)) {
                                $ret['ret_begin'] = Language::get('Vehicles cannot be used at the selected time');
                            }
                        } else {
                            // วันที่ ไม่ถูกต้อง
                            $ret['ret_end'] = Language::get('End date must be greater than begin date');
                        }
                        if ($save['travelers'] == 0) {
                            // ไม่ได้กรอก travelers
                            $ret['ret_travelers'] = 'Please fill in';
                        }
                        if ($save['status'] == 1 && $save['chauffeur'] == 0) {
                            // ไม่ได้กรอก chauffeur
                            $ret['ret_chauffeur'] = 'Please select';
                        } elseif ($save['status'] == 2 && $save['reason'] == '') {
                            // ไม่ได้กรอก reason
                            $ret['ret_reason'] = 'Please fill in';
                        }
                        if (empty($ret)) {
                            // save
                            $this->db()->update($this->getTableName('car_reservation'), $index->id, $save);
                            // อัปเดต datas
                            $car_reservation_data = $this->getTableName('car_reservation_data');
                            $this->db()->delete($car_reservation_data, array('reservation_id', $index->id), 0);
                            foreach ($datas as $key => $value) {
                                if ($value != '') {
                                    $this->db()->insert($car_reservation_data, array(
                                        'reservation_id' => $index->id,
                                        'name' => $key,
                                        'value' => $value,
                                    ));
                                }
                            }
                            if ($request->post('send_mail')->toBoolean()) {
                                // ส่งอีเมลไปยังผู้ที่เกี่ยวข้อง
                                $save['id'] = $index->id;
                                $ret['alert'] = \Car\Email\Model::send($index->username, $index->name, $save);
                            } else {
                                // คืนค่า
                                $ret['alert'] = Language::get('Saved successfully');
                            }
                            $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'car-report', 'status' => $save['status']));
                            // เคลียร์
                            $request->removeToken();
                        }
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
