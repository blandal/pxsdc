<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\ProductSku;

class OrderController extends Controller{
    public function fmtdata($data){
        if(is_array($data)){
            foreach($data as $k => &$item){
                if(is_array($item)){
                    if(is_array(array_values($item)[0])){
                        $this->fmtdata($item);
                    }
                }
                $item   = json_encode($item, JSON_UNESCAPED_UNICODE);
            }
            $data   = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
    }
    public function orders(Request $request){
        $data   = '{"itemEditDTO":{"itemId":737636632327,"itemSkuList":[{"barcode":"699042742824","itemSkuId":5263300238673,"itemWeight":300,"price":16.8,"productSkuId":"5261895270553","quantity":2,"salePropertyList":[{"elePropText":null,"eleValueText":null,"images":null,"inputValue":true,"levelSource":null,"propId":168606316,"propText":"规格","showImage":null,"valueId":-1,"valueText":"米白小熊30/31（内长17.5）"}],"skuOuterId":"1695330409367646220"},{"barcode":"840920943418","itemSkuId":5263300238674,"itemWeight":300,"price":16.8,"productSkuId":"5261895270554","quantity":2,"salePropertyList":[{"elePropText":null,"eleValueText":null,"images":null,"inputValue":true,"levelSource":null,"propId":168606316,"propText":"规格","showImage":null,"valueId":-1,"valueText":"粉红小熊28/29（内长16.5）"}],"skuOuterId":"1695330783432269838"},{"barcode":"166409611015","itemSkuId":5263300238675,"itemWeight":300,"price":16.8,"productSkuId":"5261895270555","quantity":1,"salePropertyList":[{"elePropText":null,"eleValueText":null,"images":null,"inputValue":true,"levelSource":null,"propId":168606316,"propText":"规格","showImage":null,"valueId":-1,"valueText":"米白小熊28/29（内长16.5）"}],"skuOuterId":"1695330079330283575"},{"barcode":"535407761489","itemSkuId":5263300238676,"itemWeight":300,"price":16.8,"productSkuId":"5261895270556","quantity":2,"salePropertyList":[{"elePropText":null,"eleValueText":null,"images":null,"inputValue":true,"levelSource":null,"propId":168606316,"propText":"规格","showImage":null,"valueId":-1,"valueText":"粉红小熊30/31（内长17.5）"}],"skuOuterId":"1695331380281843790"}],"fromChannel":"ITEM_EDIT","sellerId":"2216508507961","storeId":1097214140}}';

        
        $data       = json_decode($data, true);
        dd($data);
        if(is_array($data)){
            foreach($data as &$item){
                if(is_array($item)){
                    foreach($item as &$val){
                        if(is_array($val)){
                            $val    = json_encode($val, JSON_UNESCAPED_UNICODE);
                        }
                    }
                    $item   = json_encode($item, JSON_UNESCAPED_UNICODE);
                }
            }
            $data   = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $data   = str_replace('\\\\\\\\\\\\\/', '/', $data);
        // dd($data);
        // $data       = '{"itemEditDTO":"{\"itemId\":737636632327,\"itemSkuList\":[\"{\\\"barcode\\\":\\\"699042742824\\\",\\\"itemSkuId\\\":5263300238673,\\\"itemWeight\\\":300,\\\"price\\\":16.8,\\\"productSkuId\\\":\\\"5261895270553\\\",\\\"quantity\\\":2,\\\"salePropertyList\\\":[{\\\"elePropText\\\":null,\\\"eleValueText\\\":null,\\\"images\\\":null,\\\"inputValue\\\":true,\\\"levelSource\\\":null,\\\"propId\\\":168606316,\\\"propText\\\":\\\"规格\\\",\\\"showImage\\\":null,\\\"valueId\\\":-1,\\\"valueText\\\":\\\"米白小熊30/31（内长17.5）\\\"}],\\\"skuOuterId\\\":\\\"1695330409367646220\\\"}\",\"{\\\"barcode\\\":\\\"840920943418\\\",\\\"itemSkuId\\\":5263300238674,\\\"itemWeight\\\":300,\\\"price\\\":16.8,\\\"productSkuId\\\":\\\"5261895270554\\\",\\\"quantity\\\":2,\\\"salePropertyList\\\":[{\\\"elePropText\\\":null,\\\"eleValueText\\\":null,\\\"images\\\":null,\\\"inputValue\\\":true,\\\"levelSource\\\":null,\\\"propId\\\":168606316,\\\"propText\\\":\\\"规格\\\",\\\"showImage\\\":null,\\\"valueId\\\":-1,\\\"valueText\\\":\\\"粉红小熊28/29（内长16.5）\\\"}],\\\"skuOuterId\\\":\\\"1695330783432269838\\\"}\",\"{\\\"barcode\\\":\\\"166409611015\\\",\\\"itemSkuId\\\":5263300238675,\\\"itemWeight\\\":300,\\\"price\\\":16.8,\\\"productSkuId\\\":\\\"5261895270555\\\",\\\"quantity\\\":1,\\\"salePropertyList\\\":[{\\\"elePropText\\\":null,\\\"eleValueText\\\":null,\\\"images\\\":null,\\\"inputValue\\\":true,\\\"levelSource\\\":null,\\\"propId\\\":168606316,\\\"propText\\\":\\\"规格\\\",\\\"showImage\\\":null,\\\"valueId\\\":-1,\\\"valueText\\\":\\\"米白小熊28/29（内长16.5）\\\"}],\\\"skuOuterId\\\":\\\"1695330079330283575\\\"}\",\"{\\\"barcode\\\":\\\"535407761489\\\",\\\"itemSkuId\\\":5263300238676,\\\"itemWeight\\\":300,\\\"price\\\":16.8,\\\"productSkuId\\\":\\\"5261895270556\\\",\\\"quantity\\\":2,\\\"salePropertyList\\\":[{\\\"elePropText\\\":null,\\\"eleValueText\\\":null,\\\"images\\\":null,\\\"inputValue\\\":true,\\\"levelSource\\\":null,\\\"propId\\\":168606316,\\\"propText\\\":\\\"规格\\\",\\\"showImage\\\":null,\\\"valueId\\\":-1,\\\"valueText\\\":\\\"粉红小熊30/31（内长17.5）\\\"}],\\\"skuOuterId\\\":\\\"1695331380281843790\\\"}\"],\"fromChannel\":\"ITEM_EDIT\",\"sellerId\":\"2216508507961\",\"storeId\":1097214140}"}';
        $data       = str_replace('\\', '\\\\', $data);


        // {"itemEditDTO":"{\\"itemId\\":737636632327,\\"itemSkuList\\":[\\"{\\\\\\"barcode\\\\\\":\\\\\\"699042742824\\\\\\",\\\\\\"itemSkuId\\\\\\":5263300238673,\\\\\\"itemWeight\\\\\\":300,\\\\\\"price\\\\\\":16.8,\\\\\\"productSkuId\\\\\\":\\\\\\"5261895270553\\\\\\",\\\\\\"quantity\\\\\\":2,\\\\\\"salePropertyList\\\\\\":[{\\\\\\"elePropText\\\\\\":null,\\\\\\"eleValueText\\\\\\":null,\\\\\\"images\\\\\\":null,\\\\\\"inputValue\\\\\\":true,\\\\\\"levelSource\\\\\\":null,\\\\\\"propId\\\\\\":168606316,\\\\\\"propText\\\\\\":\\\\\\"规格\\\\\\",\\\\\\"showImage\\\\\\":null,\\\\\\"valueId\\\\\\":-1,\\\\\\"valueText\\\\\\":\\\\\\"米白小熊30/31（内长17.5）\\\\\\"}],\\\\\\"skuOuterId\\\\\\":\\\\\\"1695330409367646220\\\\\\"}\\",\\"{\\\\\\"barcode\\\\\\":\\\\\\"840920943418\\\\\\",\\\\\\"itemSkuId\\\\\\":5263300238674,\\\\\\"itemWeight\\\\\\":300,\\\\\\"price\\\\\\":16.8,\\\\\\"productSkuId\\\\\\":\\\\\\"5261895270554\\\\\\",\\\\\\"quantity\\\\\\":2,\\\\\\"salePropertyList\\\\\\":[{\\\\\\"elePropText\\\\\\":null,\\\\\\"eleValueText\\\\\\":null,\\\\\\"images\\\\\\":null,\\\\\\"inputValue\\\\\\":true,\\\\\\"levelSource\\\\\\":null,\\\\\\"propId\\\\\\":168606316,\\\\\\"propText\\\\\\":\\\\\\"规格\\\\\\",\\\\\\"showImage\\\\\\":null,\\\\\\"valueId\\\\\\":-1,\\\\\\"valueText\\\\\\":\\\\\\"粉红小熊28/29（内长16.5）\\\\\\"}],\\\\\\"skuOuterId\\\\\\":\\\\\\"1695330783432269838\\\\\\"}\\",\\"{\\\\\\"barcode\\\\\\":\\\\\\"166409611015\\\\\\",\\\\\\"itemSkuId\\\\\\":5263300238675,\\\\\\"itemWeight\\\\\\":300,\\\\\\"price\\\\\\":16.8,\\\\\\"productSkuId\\\\\\":\\\\\\"5261895270555\\\\\\",\\\\\\"quantity\\\\\\":1,\\\\\\"salePropertyList\\\\\\":[{\\\\\\"elePropText\\\\\\":null,\\\\\\"eleValueText\\\\\\":null,\\\\\\"images\\\\\\":null,\\\\\\"inputValue\\\\\\":true,\\\\\\"levelSource\\\\\\":null,\\\\\\"propId\\\\\\":168606316,\\\\\\"propText\\\\\\":\\\\\\"规格\\\\\\",\\\\\\"showImage\\\\\\":null,\\\\\\"valueId\\\\\\":-1,\\\\\\"valueText\\\\\\":\\\\\\"米白小熊28/29（内长16.5）\\\\\\"}],\\\\\\"skuOuterId\\\\\\":\\\\\\"1695330079330283575\\\\\\"}\\",\\"{\\\\\\"barcode\\\\\\":\\\\\\"535407761489\\\\\\",\\\\\\"itemSkuId\\\\\\":5263300238676,\\\\\\"itemWeight\\\\\\":300,\\\\\\"price\\\\\\":16.8,\\\\\\"productSkuId\\\\\\":\\\\\\"5261895270556\\\\\\",\\\\\\"quantity\\\\\\":2,\\\\\\"salePropertyList\\\\\\":[{\\\\\\"elePropText\\\\\\":null,\\\\\\"eleValueText\\\\\\":null,\\\\\\"images\\\\\\":null,\\\\\\"inputValue\\\\\\":true,\\\\\\"levelSource\\\\\\":null,\\\\\\"propId\\\\\\":168606316,\\\\\\"propText\\\\\\":\\\\\\"规格\\\\\\",\\\\\\"showImage\\\\\\":null,\\\\\\"valueId\\\\\\":-1,\\\\\\"valueText\\\\\\":\\\\\\"粉红小熊30/31（内长17.5）\\\\\\"}],\\\\\\"skuOuterId\\\\\\":\\\\\\"1695331380281843790\\\\\\"}\\"],\\"fromChannel\\":\\"ITEM_EDIT\\",\\"sellerId\\":\\"2216508507961\\",\\"storeId\\":1097214140}"}

        // {"itemEditDTO":"{\\"itemId\\":737636632327,\\"itemSkuList\\":\\"[{\\\\\\"barcode\\\\\\":\\\\\\"699042742824\\\\\\",\\\\\\"itemSkuId\\\\\\":5263300238673,\\\\\\"itemWeight\\\\\\":300,\\\\\\"price\\\\\\":16.8,\\\\\\"productSkuId\\\\\\":\\\\\\"5261895270553\\\\\\",\\\\\\"quantity\\\\\\":2,\\\\\\"salePropertyList\\\\\\":[{\\\\\\"elePropText\\\\\\":null,\\\\\\"eleValueText\\\\\\":null,\\\\\\"images\\\\\\":null,\\\\\\"inputValue\\\\\\":true,\\\\\\"levelSource\\\\\\":null,\\\\\\"propId\\\\\\":168606316,\\\\\\"propText\\\\\\":\\\\\\"规格\\\\\\",\\\\\\"showImage\\\\\\":null,\\\\\\"valueId\\\\\\":-1,\\\\\\"valueText\\\\\\":\\\\\\"米白小熊30/31（内长17.5）\\\\\\"}],\\\\\\"skuOuterId\\\\\\":\\\\\\"1695330409367646220\\\\\\"},{\\\\\\"barcode\\\\\\":\\\\\\"840920943418\\\\\\",\\\\\\"itemSkuId\\\\\\":5263300238674,\\\\\\"itemWeight\\\\\\":300,\\\\\\"price\\\\\\":16.8,\\\\\\"productSkuId\\\\\\":\\\\\\"5261895270554\\\\\\",\\\\\\"quantity\\\\\\":2,\\\\\\"salePropertyList\\\\\\":[{\\\\\\"elePropText\\\\\\":null,\\\\\\"eleValueText\\\\\\":null,\\\\\\"images\\\\\\":null,\\\\\\"inputValue\\\\\\":true,\\\\\\"levelSource\\\\\\":null,\\\\\\"propId\\\\\\":168606316,\\\\\\"propText\\\\\\":\\\\\\"规格\\\\\\",\\\\\\"showImage\\\\\\":null,\\\\\\"valueId\\\\\\":-1,\\\\\\"valueText\\\\\\":\\\\\\"粉红小熊28/29（内长16.5）\\\\\\"}],\\\\\\"skuOuterId\\\\\\":\\\\\\"1695330783432269838\\\\\\"},{\\\\\\"barcode\\\\\\":\\\\\\"166409611015\\\\\\",\\\\\\"itemSkuId\\\\\\":5263300238675,\\\\\\"itemWeight\\\\\\":300,\\\\\\"price\\\\\\":16.8,\\\\\\"productSkuId\\\\\\":\\\\\\"5261895270555\\\\\\",\\\\\\"quantity\\\\\\":1,\\\\\\"salePropertyList\\\\\\":[{\\\\\\"elePropText\\\\\\":null,\\\\\\"eleValueText\\\\\\":null,\\\\\\"images\\\\\\":null,\\\\\\"inputValue\\\\\\":true,\\\\\\"levelSource\\\\\\":null,\\\\\\"propId\\\\\\":168606316,\\\\\\"propText\\\\\\":\\\\\\"规格\\\\\\",\\\\\\"showImage\\\\\\":null,\\\\\\"valueId\\\\\\":-1,\\\\\\"valueText\\\\\\":\\\\\\"米白小熊28/29（内长16.5）\\\\\\"}],\\\\\\"skuOuterId\\\\\\":\\\\\\"1695330079330283575\\\\\\"},{\\\\\\"barcode\\\\\\":\\\\\\"535407761489\\\\\\",\\\\\\"itemSkuId\\\\\\":5263300238676,\\\\\\"itemWeight\\\\\\":300,\\\\\\"price\\\\\\":16.8,\\\\\\"productSkuId\\\\\\":\\\\\\"5261895270556\\\\\\",\\\\\\"quantity\\\\\\":2,\\\\\\"salePropertyList\\\\\\":[{\\\\\\"elePropText\\\\\\":null,\\\\\\"eleValueText\\\\\\":null,\\\\\\"images\\\\\\":null,\\\\\\"inputValue\\\\\\":true,\\\\\\"levelSource\\\\\\":null,\\\\\\"propId\\\\\\":168606316,\\\\\\"propText\\\\\\":\\\\\\"规格\\\\\\",\\\\\\"showImage\\\\\\":null,\\\\\\"valueId\\\\\\":-1,\\\\\\"valueText\\\\\\":\\\\\\"粉红小熊30/31（内长17.5）\\\\\\"}],\\\\\\"skuOuterId\\\\\\":\\\\\\"1695331380281843790\\\\\\"}]\\",\\"fromChannel\\":\\"ITEM_EDIT\\",\\"sellerId\\":\\"2216508507961\\",\\"storeId\\":1097214140}"}



        $token      = 'f7f6470fd6de34ce9d817ed781a8cb05';
        $ts         = '1694590078968';
        $appkey     = '12574478';
        $sinstr     = "$token&$ts&$appkey&$data";
        dd(md5($sinstr), $sinstr);

        set_time_limit(0);
        $platform   = (int)$request->post('platform');
        $storeid    = (int)$request->post('store_id');

        // try {
        //     $instance       = Store::getInstance($storeid, $platform);

        //     $list           = $request->post('list');
        //     $list           = json_decode($list, true);
        //     if(!$list || !is_array($list)){
        //         return $this->error('data 数据解析错误!');
        //     }
        //     if(!$instance->saveOrders($list)){
        //         return $this->error(implode("<br>\r\n", $instance->errs()));
        //     }
        //     $last   = Order::select('orderid', 'store_id', 'platform_id', 'status')->where('platform_id', $platform)->where('store_id', $storeid)->orderByDesc('id')->first();
        //     return $this->success($last, '成功!');
        // } catch (\Exception $e) {
        //     return $this->error($e->getMessage());
        // }


        // // $plt        = $this->checkPlatform($platform);
        // $plt        = Store::getInstance($storeid, $platform);
        // if(!($plt instanceof Platform)){
        //     return $plt;
        // }

        $list       = $request->post('list');
        $list       = is_string($list) ? json_decode($list, true) : $list;
        if(!$list || !is_array($list)){
            return $this->error('data 数据解析错误!');
        }

        // try {
            $instance   = Store::getInstance($storeid, $platform);//where('store_id', $storeid)->where('platform_id', $platform)->first();
            $newOps     = $instance->saveOrders($list);
            if(empty($newOps)){
                $errs   = $instance->errs() ? implode("<br>\r\n", $instance->errs()) : '添加为空!';
                return $this->error($errs);
            }
            ProductSku::newOrderForChangeStocks($newOps);
            return $this->success(null, '成功!');
        // } catch (\Exception $e) {
        //     return $this->error($e->getMessage());
        // }
    }
}
