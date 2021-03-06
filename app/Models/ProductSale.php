<?php

declare (strict_types=1);
namespace App\Models;

use App\Models\ProductClassify;
use Core\Common\Container\Redis;
use Hyperf\Di\Annotation\Inject;
use Core\Common\Extend\Helpers\ArrayHelpers;

/**
 * @property int $id 
 * @property int $admin_id 
 * @property int $pid 
 * @property int $cid 
 * @property int $access 
 * @property string $kind_name 
 * @property string $name 
 * @property string $titile 
 * @property float $price 
 * @property string $icon 
 * @property string $recommend
 * @property int $stocks 
 * @property int $sales 
 * @property string $penalty 
 * @property string $first_desc 
 * @property int $sort 
 * @property string $deleted_at 
 * @property-read string $created_at
 * @property bool $status
 * @property-read string $updated_at
 */
class ProductSale extends BaseModel
{
    /**
     * @Inject()
     * @var ProductClassify
     */
    private $ProductClassify;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'product_sale';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['id', 'admin_id', 'pid', 'cid', 'access', 'kind_name', 'name', 'titile', 'price', 'icon', 'recommend', 'stocks', 'sales', 'penalty', 'first_desc', 'sort', 'status', 'created_at', 'updated_at', 'deleted_at','commission'];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['id' => 'integer', 'admin_id' => 'integer', 'pid' => 'integer', 'cid' => 'integer', 'access' => 'integer', 'price' => 'float', 'recommend' => 'integer', 'stocks' => 'integer', 'sales' => 'integer', 'sort' => 'integer', 'status' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];

    protected $appends = [
        'cid_name',
    ];

    protected $searchFields = ['id', 'name', 'kind_name'];

    public function getCidNameAttribute() : string
    {
        if(empty($this->attributes['cid'])){
            return '';
        }
        return ArrayHelpers::searchColumn($this->ProductClassify->getList(), 'id', (string)$this->attributes['cid'], 'name');
    }

    public function getPriceAttribute() : string
    {
        return sprintf("%.2f",$this->attributes['price']);
    }

    public function getStatusAttribute() : bool
    {
        return $this->attributes['status'] = $this->attributes['status'] ? true : false;
    }

    public function getCreatedAtAttribute() : string
    {
        if ('0000-00-00 00:00:00' === (string) $this->attributes['created_at']) {
            return '-';
        }
        return (string) $this->attributes['created_at'];
    }

    public function getUpdatedAtAttribute() : string
    {
        if ('0000-00-00 00:00:00' === (string) $this->attributes['updated_at']) {
            return '-';
        }
        return (string) $this->attributes['updated_at'];
    }

    /**
     * ????????????????????????
     * getPidKindName
     * @param  int  $pid
     *
     * @return string
     * author MengShuai <133814250@qq.com>
     * date 2021/01/04 15:47
     */
    public function getPidKindName(int $pid) : string
    {
       $name = $this->query()->where(['id' => $pid])->value('kind_name');
       return isset($name) ? (string)$name : '';
    }

    public function product_classify()
    {
        return $this->hasOne(ProductClassify::class, 'id', 'cid');
    }
}