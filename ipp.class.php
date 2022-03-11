<?php
class IPPServer{
    private static $define = [
        "version-number" => "\x02\x00", // IPP协议版本号 v2.0
        "tag-end" => "\x03",// 标签结束标志
        "status-ok" => "\x00\x00", // 返回，成功状态码
        "status-bad-request" => "\x04\x00",// 返回，错误的请求
        ];
    private static $tags = [
        "\x44" => "keywords",
        "\x47" => "charset",
        "\x48" => "natural-lang",
        ];
    private static $tag_group = [
        "\x01" => "oprations_tag",
        "\x02" => "jobs_tag",
        "\x04" => "printer_tag",
        "\x05" => "unsupported",
        ];
    private static $supported = [
        "\x02" => "Print-Job",
        "\x04" => "Vaildate-Job",// 应该是验证当前能否执行任务
        "\x08" => "Cancel-Job",
        "\x39" => "Cancel-My-Jobs",
        "\x09" => "Get-Job-Attributes",
        "\x0a" => "Get-Jobs",
        "\x0b" => "Get-Printer-Attributes",// 获得打印参数
        "\x05" => "Create-Job",
        "\x06" => "Send-Document",
        "\x13" => "Set-Printer-Attributes",
        "\x03" => "Print-URL",
        "\x07" => "Send-URL",
        "\x3b" => "Close-Job",
        "\x3c" => "Identify-Printer",
        ];
    public $request; // 请求内容
    public $version; // 协议版本号
    public $opration; // 请求操作
    public $request_id; // 请求id
    public $attrs; // 请求参数
    public $data;// 请求参数
    
    public function __construct()
    {   $r = file_get_contents("php://input");
        $this->request = $r;
        $this->version = substr($r, 0, 2);
        $this->opration = substr($r, 2, 2);
        $this->request_id = substr($r, 4, 4);
        $this->attrs = false;
        if(isset(self::$tag_group[substr($r,8,1)])) // 存在操作标志
            $this->attrs = strrpos($r,self::$define["tag-end"],8) - 8;
        else 
            $this->data = 8;
        if($this->attrs !== false){// 如果存在结尾则存储参数
            $this->data = $this->attrs;
            $this->attrs = substr($r,8,$this->attrs);
            $this->attrs = $this->attrsToArray();
        }
        $this->data = substr($r,$this->data);
        return true;
    }
    
    
    public function operate(String $opration = ""){
        if(!is_array($this->attrs))
            return false;
        if(empty($opration))
            $opration = $this->opration;
        if(strlen($opration) == 2)
            $opration = substr($opration,1,1);
        switch (self::$supported[$opration]) {
            case 'Get-Printer-Attributes':
                $this->printerAttributes($this->attrs["keywords"]);
                break;
            
            default:
                // code...
                break;
        }
    }
    
    public function printerAttributes(Array $keywords = []){
        $data = [];
        foreach ($keywords as $v){
            $value = "";
            switch($v){
                case 'operations-supported':
                    $value = [];
                    foreach (self::$supported as $tagK => $tagV)
                        $value[] = "\x00\x00\x00".$tagK;
                    break;
                case 'printer-uri-supported':
                    $value = [
                        "ipp://pay.timewk.cn/ipp/test"
                        ];
                    break;
                default:continue;break;
            }
            $data[$v] = $value;
        }
        
    }
    
    public function attrsToArray(){
        if(is_array($this->attrs))
            return true;
        $attrs = substr($this->attrs, 0);
        $tag = "";
        $data = [];
        $len = strlen($attrs);
        for($pos = 0;$pos <= $len;$pos++){
            if(substr($attrs, $pos, 1) == self::$define["tag-end"])
                break; 
            if(isset(self::$tag_group[substr($attrs,$pos,1)]))
                $tag = self::$tag_group[substr($attrs,$pos,1)];
            if(!isset(self::$tags[substr($attrs, $pos, 1)]))
                continue;// 未识别的标签
            $attr = self::$tags[substr($attrs, $pos, 1)];
            $length = unpack("n",substr($attrs, $pos + 1, 2))[1];// 参数名长度
            $pos = $pos + 3 + $length;// 跳过参数名
            $length = unpack("n",substr($attrs,$pos,2))[1];// 参数值长度
            $length = unpack("a*",substr($attrs,$pos + 2,$length))[1];//参数值
            if(isset($data[$tag][$attr])){
                if(is_array($data[$tag][$attr]))
                    $data[$tag][$attr][] = $length;
                else
                    $data[$tag][$attr] = [
                    $data[$tag][$attr],
                    $length
                    ];
            }else
                $data[$tag][$attr] = $length;
                
        }
        return $data;
    }
    
    public function arrayToBin(Array $attrs){
        $bin = "";
        foreach ($attrs as $k => $v){
            if(!is_array($v))
                $v = [$v];
            foreach ($v as $attr){
                $tag = array_search($k,self::$tags);
                if(!$tag){
                    end(self::$tags);
                    $tag = key(self::$tags);
                    reset(self::$tags);
                }
                $bin .= $tag;
                $len = dechex(strlen(self::$tags[$tag]));
                if(strlen($len) < 2)
                    $len.="\x00";
                $bin .= $len;
                $bin .= bin2hex(self::$tags[$tag]);
                
            }
        }
    }
    
    public function output(String $status){
        ob_start();
        $header = [
            "Transfer-Encoding" => "chunked",
            "Cache-Control" => "must-revalidate, max-age=0",
            "Pragma" => "no-cache",
            "Content-Type" => "application/ipp",
            "Server" => "TimeTree Web Printer - 1.0;" 
            ];
        foreach ($header as $k => $v)
            header($k.": ".$v);
        if(isset(self::$define["status-".$status]))
            $status = self::$define["status-".$status];
        else
            $status = self::$define["status-bad-request"];
        $pack = [
            $this->version,
            $status,
            $this->request_id
            ];
        $tag_pack = [
            $tag,
            $tag_len, //2b
            $tag_name,//ascii
            $value_len,//2b
            $value,//ascii
            ];
        echo "\x00\x01\x03";
        ob_end_flush();
    }
}