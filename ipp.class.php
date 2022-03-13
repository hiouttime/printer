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
        "\x45" => "printer-uri-supported",
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
        "\x06" => "Send-Document",
        "\x07" => "Send-URL",
        "\x3b" => "Close-Job",
        "\x3c" => "Identify-Printer",
        ];
    private static $types = [
        "true" => "\x01",
        "false" => "\x00",
        "boolean" => "\x22",
        "array" => "\x23", // IPP_TAG_ENUM
        "text" => "\x41",
        "name" => "\x42",
        "url" => "\x45",
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
            $this->attrs = strrpos($r,self::$define["tag-end"],8);
        else 
            $this->data = 8;
        if($this->attrs !== false){// 如果存在结尾则存储参数
            $this->data = $this->attrs + 1;
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
                $this->printerAttributes($this->attrs["oprations_tag"]["keywords"]);
                break;
            case 'Vaildate-Job':
                $this->output("ok");
                break;
            case 'Send-Document':
                $this->saveDocument();
                break;
            default:break;
        }
    }
    
    public function printerAttributes(Array $keywords){
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
                        "ipp://printer"
                        ];
                    break;
                case 'uri-authentication-supported':
                    $value = [
                        "requesting-user-name"
                        ];
                    break;
                case 'printer-location':
                    $value = "中国";
                    break;
                case 'printer-more-info':
                    $value = "https://example.com";
                    break;
                case 'printer-info':
                    $value = "测试打印姬";
                    break;
                case 'printer-dns-sd-name';
                    $value = "测试打印姬";
                    break;
                case 'printer-make-and-model':
                    $value = "GuGuGu";
                    break;
                case 'printer-is-accepting-jobs':
                    $value = true;
                    break;
                case 'printer-uuid':
                    $value = "urn:uuid:daa7f557-bc16-fd42-8bde-21ca3984f6ad";
                    break;
                default:continue 2;break;
            }
            $data[$v] = $value;
        }
        $this->output("ok",$this->arrayToBin("printer_tag",$data));
    }
    
    public function attrsToArray(){
        if(is_array($this->attrs))
            return true;
        $attrs = substr($this->attrs, 0);
        $value = "";$tag = "";
        $data = [];
        $len = strlen($attrs);
        for($pos = 0;$pos < $len;$pos++){
            if(substr($attrs, $pos, 1) == self::$define["tag-end"])
                break; 
            if(isset(self::$tag_group[substr($attrs, $pos, 1)]))
                $tag = self::$tag_group[substr($attrs, $pos, 1)];
            if(!isset($tag))
                continue;
            $attr = self::$tags[substr($attrs, $pos + 1, 1)];
            if(empty($attr))// 跳过未做支持的标签
                continue;
            $length = unpack("n",substr($attrs, $pos + 2, 2))[1];// 参数名长度
            $pos = $pos + 4 + $length;// 跳过参数名
            $length = unpack("n",substr($attrs,$pos,2))[1];// 参数值长度
            $value = unpack("a*",substr($attrs,$pos + 2,$length))[1];//参数值
            $pos = $pos + $length;// 跳过参数
            if(!is_array($data[$tag]))
                $data[$tag] = [];
            if(isset($data[$tag][$attr])){
                if(is_array($data[$tag][$attr]))
                    $data[$tag][$attr][] = $value;
                else
                    $data[$tag][$attr] = [
                    $data[$tag][$attr],
                    $value
                    ];
            }else
                $data[$tag][$attr] = $value;
                
        }
        return $data;
    }
    
    public function arrayToBin(String $tag, Array $attrs){
        $bin = array_search($tag,self::$tag_group);
        if(empty($bin))
            return false;
        $length = "";
        foreach ($attrs as $k => $v){
            $tag = array_search($k,self::$tags);
            if($tag)
                $bin.=$tag;
            else{
                $tag = gettype($v);
                switch($tag){
                    case 'boolean':
                        $v = $v?self::$types["true"]:self::$types["false"];
                    break;
                    case 'array': case 'object':break;
                    default:
                        $tag = $this->typeDetect($v);
                        break;
                }
                if(isset(self::$types[$tag])){
                    $tag = self::$types[$tag];
                    $bin.=$tag;
                }else
                    continue;
            }
            $length = pack("n",strlen($k));
            if(strlen($length) < 2)
                $length = "\x00".$length;
            $bin .= $length.$k;
            if(is_array($v))// 集合
                foreach ($v as $vk => $vv){
                    if($vk != 0)
                        $bin .= $tag."\x00\x00";
                    $length = pack("n",strlen($vv));
                    if(strlen($length) < 2)
                        $length = "\x00".$length;
                    $bin .= $length.$vv;
                }
            else{
                $length = pack("n",strlen($v));
                if(strlen($length) < 2)
                    $length = "\x00".$length;
                $bin .= $length.$v;
            }
        }
        return $bin;
    }
    
    public function typeDetect($v){// 自定义类型判断
        if(preg_match('/[a-zA-z]+:\/\/[^\s]*/', $v))
            return "url";
        if(preg_match('/\S+:\S+:\S+/', $v))
            return "url";
        return "text";
    }
    
    public function saveDocument(){
        
    }
    
    public function output(String $status,String $bin_data = ""){
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
            $this->request_id,
            $this->arrayToBin("oprations_tag",[
                "charset" => "utf-8",
                "natural-lang" => "zh-cn"
                ]),
            $bin_data,
            self::$define["tag-end"]
            ];
        echo implode("",$pack);
        ob_end_flush();
    }
}