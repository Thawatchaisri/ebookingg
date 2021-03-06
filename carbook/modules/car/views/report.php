<?php
/**
 * @filesource modules/car/views/report.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Car\Report;

use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;

/**
 * module=car-report
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * @var array
     */
    private $status;
    /**
     * @var object
     */
    private $chauffeur;

    /**
     * ตารางรายการจอง
     *
     * @param Request $request
     * @param array  $params
     *
     * @return string
     */
    public function render(Request $request, $params)
    {
        $this->status = $params['statuses'];
        // พนักงานกับรถ
        $this->chauffeur = array(-1 => '{LNG_Self drive}', 0 => '{LNG_Not specified (anyone)}')+\Car\Chauffeur\Model::init()->toSelect();
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Car\Report\Model::toDataTable($params),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('carReport_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('carReport_sort', 'today,create_date DESC')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('id', 'today', 'remain', 'vehicle_id', 'end'),
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('name', 'contact', 'phone'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/car/model/report/action',
            'actionCallback' => 'dataTableActionCallback',
            'actions' => array(
                array(
                    'id' => 'action',
                    'class' => 'ok',
                    'text' => '{LNG_With selected}',
                    'options' => array(
                        'delete' => '{LNG_Delete}',
                    ),
                ),
            ),
            /* ตัวเลือกด้านบนของตาราง ใช้จำกัดผลลัพท์การ query */
            'filters' => array(
                array(
                    'name' => 'from',
                    'type' => 'date',
                    'text' => '{LNG_Date}',
                    'value' => $params['from'],
                ),
                array(
                    'name' => 'vehicle_id',
                    'text' => '{LNG_Vehicle}',
                    'options' => array(0 => '{LNG_all items}')+\Car\Vehicles\Model::toSelect(),
                    'value' => $params['vehicle_id'],
                ),
                array(
                    'name' => 'chauffeur',
                    'text' => '{LNG_Chauffeur}',
                    'options' => array(-2 => '{LNG_all items}') + $this->chauffeur,
                    'value' => $params['chauffeur'],
                ),
                array(
                    'name' => 'status',
                    'text' => '{LNG_Status}',
                    'options' => array(-1 => '{LNG_all items}') + $this->status,
                    'value' => $params['status'],
                ),
            ),
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'detail' => array(
                    'text' => '{LNG_Vehicle usage details}',
                    'class' => 'topic',
                ),
                'number' => array(
                    'text' => '{LNG_Vehicle number}',
                    'sort' => 'number',
                ),
                'contact' => array(
                    'text' => '{LNG_Contact name}',
                ),
                'phone' => array(
                    'text' => '{LNG_Phone}',
                    'class' => 'center',
                ),
                'begin' => array(
                    'text' => '{LNG_Date}',
                    'class' => 'center',
                    'sort' => 'begin',
                ),
                'chauffeur' => array(
                    'text' => '{LNG_Chauffeur}',
                    'class' => 'center',
                    'sort' => 'chauffeur',
                ),
                'create_date' => array(
                    'text' => '{LNG_Created}',
                    'class' => 'center',
                    'sort' => 'create_date',
                ),
                'status' => array(
                    'text' => '{LNG_Status}',
                    'class' => 'center',
                ),
                'reason' => array(
                    'text' => '{LNG_Reason}',
                    'class' => 'topic',
                ),
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'contact' => array(
                    'class' => 'nowrap',
                ),
                'phone' => array(
                    'class' => 'center',
                ),
                'begin' => array(
                    'class' => 'center nowrap',
                ),
                'chauffeur' => array(
                    'class' => 'center',
                ),
                'create_date' => array(
                    'class' => 'center nowrap',
                ),
                'status' => array(
                    'class' => 'center',
                ),
            ),
            /* ฟังก์ชั่นตรวจสอบการแสดงผลปุ่มในแถว */
            'onCreateButton' => array($this, 'onCreateButton'),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                'edit' => array(
                    'class' => 'icon-edit button green',
                    'href' => $uri->createBackUri(array('module' => 'car-order', 'id' => ':id')),
                    'text' => '{LNG_Edit}',
                ),
            ),
        ));
        // save cookie
        setcookie('carReport_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('carReport_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);
        // คืนค่า HTML
        return $table->render();
    }

    /**
     * จัดรูปแบบการแสดงผลในแต่ละแถว
     *
     * @param array  $item ข้อมูลแถว
     * @param int    $o    ID ของข้อมูล
     * @param object $prop กำหนด properties ของ TR
     *
     * @return array
     */
    public function onRow($item, $o, $prop)
    {
        if ($item['today'] == 1) {
            $prop->class = 'bg3';
        }
        $item['phone'] = '<a href="tel:'.$item['phone'].'">'.$item['phone'].'</a>';
        $item['begin'] = self::dateRange($item);
        $item['create_date'] = Date::format($item['create_date'], 'd M Y').'<br>{LNG_Time} '.Date::format($item['create_date'], 'H:i');
        $item['chauffeur'] = isset($this->chauffeur[$item['chauffeur']]) ? $this->chauffeur[$item['chauffeur']] : '';
        $item['status'] = '<span class="term'.$item['status'].'">'.$this->status[$item['status']].'</span>';
        $item['detail'] = '<span class=two_lines title="'.$item['detail'].'">'.$item['detail'].'</span>';
        $item['reason'] = '<span class=two_lines title="'.$item['reason'].'">'.$item['reason'].'</span>';
        return $item;
    }

    /**
     * ฟังกชั่นตรวจสอบว่าสามารถสร้างปุ่มได้หรือไม่
     *
     * @param array $item
     *
     * @return array
     */
    public function onCreateButton($btn, $attributes, $item)
    {
        if ($btn == 'edit') {
            if (empty(self::$cfg->car_approving) && $item['today'] == 2) {
                return false;
            } elseif (self::$cfg->car_approving == 1 && $item['remain'] < 0) {
                return false;
            } else {
                return $attributes;
            }
        } else {
            return $attributes;
        }
    }
}
