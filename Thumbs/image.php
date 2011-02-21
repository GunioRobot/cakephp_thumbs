<?php
/*==================================
 * 
 * テキストから画像投稿サイトのサムネイルを取得
 * Author: Kain Ryu
 * Date: 2011.01.01
 * 
 * 
 * 対応サイト(2010.12.31時点)
 *		Twitpic.com
 *		movapic.com
 *		moby.to
 *		yfrog.com
 *		bctiny.com
 *		img.ly
 *		twitgoo.com
 *		imgur.com
 *		plixi.com
 *		ow.ly
 *		instagram
 *		twipple
 *		 twitrpix.com
================================== */

/*
使い方：
$obj = new Image("/var/www/cakephp/app/webroot/thumbs"); //fill in your thumbs's directory path.
$url = check_image_url("弊社オフィス前。 http://twitpic.com/3nf9y2");
echo $obj->get_thumb($url, "123465789");
*/

require_once 'simple_html_dom.php';

/*
 * 文字列から対応している画像投稿サイトのURLを一つ取得（最初に出現したもの）
 * 主に、URLを返したときのみ、インスタンスを生成させてサムネイルを取得させる
 *
 * @arg: (str)text
 * @return: (str)url
 *
 */

//change to your setting.
define('USERAGENT','YOURCLIENTNAME');

function check_image_url($text){

	//対応サイトのURLパターン一覧
	$patterns = array(
		'/http:\/\/twitpic.com\/(\w+)/',
		'/http:\/\/movapic[.]com\/pic\/(\w+)/',
		'/http:\/\/moby[.]to\/(\w+)/',
		'/http:\/\/yfrog[.]com\/(\w+)/',
		'/http:\/\/bctiny[.]com\/p(\w+)/',
		'/http:\/\/img[.]ly\/(\w+)/',
		'/http:\/\/twitgoo[.]com\/(\w+)/',
		'/http:\/\/tweetphoto[.]com\/\d+|http:\/\/plixi[.]com\/p\/\d+/',
		'/http:\/\/i[.]imgur[.]com\/(\w+)[.]jpg/',
		'/http:\/\/ow[.]ly\/i\/(\w+)/',
		'/http:\/\/instagr[.]am\/p\/(\w+)/',
		'/http:\/\/p[.]twipple[.]jp\/(\w+)/',
		'/http:\/\/twitrpix[.]com\/(\w+)/'
	);
	foreach($patterns as $pattern){
		if(preg_match($pattern, $text, $matches))
			$url = $matches[0];
	}
	if(isset($url))
		return $url;
	else
		return false;
}

class Image {

	public $http_code = null;
	public $content_type = null;
	
	public function __construct($thum_path){
		define('THUMB_DIR', $thum_path);
	}
	
	private function http($url){
		
		$ch = curl_init();
		if(defined(USERAGENT)):
			curl_setopt($ch, CURLOPT_USERAGENT, USERAGENT);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); //Locationをたどる
		//curl_setopt($ch, CURLOPT_MAXREDIRS, 3);				//Locationをたどる最大の回数を指定。ない場合、無限にたどる。
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$res = curl_exec($ch);

		$http_info = curl_getinfo($ch);
		$this->http_code = $http_info['http_code'];
		$this->content_type = $http_info['content_type'];
		curl_close($ch);

		return $res;
		
	}

	public function base36_decode($base36){
		 return base_convert($base36,36,10);
	}

	public function select_service($url){
		
		preg_match('@^(?:http://)?([^/]+)@i', $url, $match);

		if($match[1] == "twitpic.com")
			$url = $this->twitpic($url);
		elseif($match[1] == "movapic.com")
			$url = $this->movapic($url);
		elseif($match[1] ==  "moby.to")
			$url = $this->moby($url);
		elseif($match[1] ==  "yfrog.com")
			$url = $this->yfrog($url);
		elseif($match[1] ==  "bctiny.com")
			$url = $this->bctiny($url);
		elseif($match[1] ==  "img.ly")
			$url = $this->imgly($url);
		elseif($match[1] ==  "twitgoo.com")
			$url = $this->twitgoo($url);
		elseif($match[1] == "plixi.com")
			$url = $this->plixi($url);
		elseif($match[1] ==  "tweetphoto.com")
			$url = $this->plixi($url);
		elseif($match[1] ==  "i.imgur.com")
			$url = $this->imgur($url);
		elseif($match[1] ==  "ow.ly")
			$url = $this->owly($url);
		elseif($match[1] == "instagr.am" )
			$url = $this->instagram($url);
		elseif($match[1] ==  "p.twipple.jp")
			$url = $this->twipple($url);
		elseif($match[1] ==  "twitrpix.com")
			$url = $this->twitrpix($url);
		else
			$url = $this->no_thumbnail($url);

		return $url;
	}

	public function get_thumb($url, $id){
		
		$mimetype = array(
			"image/gif" => "gif",
			"image/jpeg" => "jpg",
			"image/png" => "png"
		);

		$imgurl = $this->select_service($url);
		$res = $this->http($imgurl);
		//$filename = dirname(__FILE__).THUMB_DIR.$id;
		$filename = THUMB_DIR.$id;
		if(touch($filename) && $this->http_code == 200){
			$fp = fopen($filename, 'w+');
			fwrite($fp, $res);
			fclose($fp);
		}
		//chmod(THUMB_DIR.$id,0755);
		$imginfo = getimagesize($filename);
		if( rename( $filename, $filename.".".$mimetype[ $imginfo['mime'] ] ) ){
			$filename = $id.".".$mimetype[ $imginfo['mime'] ];
		}else{
			$filename = $id;
		}

		return $filename;
	}


	public function twitpic($url){

		//“mini” or “thumb”
		$url = preg_replace('/http:\/\/twitpic[.]com\/(\w+)/', 'http://twitpic.com/show/thumb/$1', $url);

		return $url;
	}

	public function movapic($url){

		//“s” or “t”
		$url = preg_replace('/http:\/\/movapic[.]com\/pic\/(\w+)/','http://image.movapic.com/pic/s_$1.jpeg',$url);

		return $url;
	}

	public function moby($url){

		//“thumbnail”, “small”, “square” or “medium"
		$url = preg_replace('/http:\/\/moby[.]to\/(\w+)/', 'http://moby.to/$1:square', $url);

		return $url;
	}

	public function yfrog($url){

		//なし
		$url = preg_replace('/http:\/\/yfrog[.]com\/(\w+)/', 'http://yfrog.com/$1.th.jpg', $url);

		return $url;
	}

	public function bctiny($url){

		//“thumbnail”, “large”, “thumb68" or “thumb180"
		$url = preg_replace('/http:\/\/bctiny[.]com\/p(\w+)/', '$1', $url);
		$url = $this->base36_decode($url);
		$url = 'http://images.bcphotoshare.com/storages/'.$url.'/thumb180.jpg';

		return $url;
	}

	public function imgly($url){

		//“thumb” or “mini”
		$url = preg_replace('/http:\/\/img[.]ly\/(\w+)/','http://img.ly/show/thumb/$1',$url);

		return $url;
	}

	public function twitgoo($url){

		//“thumb”, “mini” or “img”
		$url = preg_replace('/http:\/\/twitgoo[.]com\/(\w+)/','http://twitgoo.com/$1/thumb',$url);

		return $url;
	}

	public function plixi($url){

		//“thumbnail”, “medium” or “big”
		$url = preg_replace('/http:\/\/tweetphoto[.]com\/\d+|http:\/\/plixi[.]com\/p\/\d+/','http://api.plixi.com/api/TPAPI.svc/imagefromurl?size=medium&url=$0',$url);

		return $url;
	}

	public function imgur($url){

		//“s” or “l”
		$url = preg_replace('/http:\/\/i[.]imgur[.]com\/(\w+)[.]jpg/', 'http://i.imgur.com/$1.jpg', $url);

		return $url;
	}

	public function owly($url){

		//なし
		$url = preg_replace('/http:\/\/ow[.]ly\/i\/(\w+)/','http://static.ow.ly/photos/thumb/$1.jpg',$url);

		return $url;
	}

	public function instagram($url){

		$res = $this->http($url);

		$html = str_get_html($res);
		foreach($html->find('div[id=photo] img.photo') as $element){
			$url =$element->src;
		}

		return $url;
	}

	public function twipple($url){

		$res = $this->http($url);

		// m or s
		$html = str_get_html($res);
		foreach($html->find('div[id=photoarea] div[id=img_box] img') as $element){
			$url = $element->src;
		}
		
		//大きい画像で表示させたい場合は、この次の行をコメントアウト
		$url = str_replace('_m.','_s.',$url);

		return $url;
	}

	public function twitrpix($url){

		//なし
		$url = preg_replace('/http:\/\/twitrpix[.]com\/(\w+)/','http://img.twitrpix.com/thumb/$1',$url);

		return $url;
	}

	public function no_thumbnail($url){

		return $url;
	}
}
?>
