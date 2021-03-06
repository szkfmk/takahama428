<?php
/**************************************
　スマホ版サイト
　商品情報の取得
　charset utf-8
***************************************/


require_once $_SERVER['DOCUMENT_ROOT'].'/php_libs/conndb.php';

class ItemInfo extends Conndb {

	public function __construct(){
		parent::__construct();
	}
	
	// 商品ページ
	public function getData($category, $itemid=null, $priceorder='index'){
		$data = array();
		if(!is_null($itemid)){
			$data = parent::itemList($itemid, "item");
		}else{
			$cat = parent::categoryList();
			for($i=0; $i<count($cat); $i++){
				if($cat[$i]['code']==$category){
					$data = parent::itemList($cat[$i]['id']);
					break;
				}
			}
			if($category=='sportswear'){
				foreach($data as $key=>$val){
					$sub[$val["id"]] = true;
				}
				$tmp = parent::itemList(2, 'tag');	// スポーツウェアにドライタグを追加
				foreach($tmp as $key1=>$val1){
					if(!array_key_exists($val1["id"], $sub)){
						$data[] = $tmp[$key1];
					}
				}
			}
		}
		
		foreach($data as $key=>$val){
			// アイテムレビュー
			$review = parent::getItemReview(array('sort'=>'post', 'itemid'=>$val['id']));
			$len = count($review);
			$r[$key]['len'] = $len;
			
			// レビュー総合評価
			$v = 0;
			for($i=0; $i<$len; $i++){
				$v += $review[$i]['vote'];
			}
			if($v==0){
				$r[$key]['ratio'] = 0;
			}else{
				$r[$key]['ratio'] = round($v/$len, 1);
			}
			$r[$key]['img'] = $this->getStar($r[$key]['ratio']);

			$r[$key]['itemid'] = $val['id'];
			$r[$key]['itemcode'] = $val['code'];
			$r[$key]['itemname'] = $val['name'];
			$r[$key]['row'] = $val['item_row'];
			$r[$key]['posid'] = $val['posid'];
			$r[$key]['price'] = $val['cost'];	// 最安単価
			
			$attr = parent::itemAttr($val['id']);
			$r[$key]['color'] = count($attr['code']);	// color count
			list($itemCode_colorCode, $sizehash) = each($attr['size']);
			$r[$key]['sizecount'] = count($sizehash);	// size count
			$part = explode('_', $itemCode_colorCode);
			$currentColorCode = $part[1];
			
			if( preg_match('/^p-/',$val['code']) && $val['i_color_code']==""){
				$suffix = '_style_0'; 
			}else{ 
				$suffix = '_'.$val['i_color_code']; 
			}
			$r[$key]['imgname'] = $val['code'].$suffix;

			list($categorykey, $categoryname) = each($attr['category']);
			$r[$key]['categorykey'] = $categorykey;
			$r[$key]['categoryname'] = $categoryname;
			
			// アイテム詳細ページ
			if(!is_null($itemid)){
				// レビューのテキストを2件まで返す
				$reviewcount = 2;
				if($len<2){
					$reviewcount = $len;
				}
				for ($i=0; $i < $reviewcount; $i++) { 
					$r[$key]['review'][$i] = $review[$i];
				}
				
				// サイズ毎の単価
				$priceHash = parent::sizePrice($val['id'], $currentColorCode);
				for($i=0; $i<count($priceHash); $i++){
					$r[$key]['size_price'][$priceHash[$i]['id']] = $priceHash[$i]['cost'];
				}
				
				// サイズ展開
				$r[$key]['size'] = $sizehash;
				foreach($sizehash as $sizeid=>$sizename){
					if($sizeid<11){								// 70-160
						$s[0][] = array($sizeid,$sizename);
					}else if($sizeid<17 || $sizeid>28){			// JS-JL, GS-GL, WS-WL
						$s[1][] = array($sizeid,$sizename);
					}else{										// XS-8L
						$s[2][] = array($sizeid,$sizename);
					}
				}
				for($i=0; $i<3; $i++){
					if(!empty($s[$i])){
						if($s[$i][0]!=$s[$i][count($s[$i])-1]){
							if($s[$i][0][0]+1==$s[$i][1][0]){
								$s[3][] = $s[$i][0][1].'-'.$s[$i][count($s[$i])-1][1];
							}else{
								for($t=0; $t<count($s[$i]); $t++){
									$s[3][] = $s[$i][$t][1];
								}
							}
						}else{
							$s[3][] = $s[$i][0][1];
						}
					}
				}
				$r[$key]['sizeseries'] = implode(', ', $s[3]);
				
				// アイテムカラー[{code:カラー名},{},...]
				$r[$key]['thumbs'] = $attr['code'];
				
				// アイテム説明と素材
				$r[$key]['explain'] = $val['i_description'];
				$r[$key]['material'] = $val['i_material'];
				
				// 寸法
				$r[$key]['measure'] = parent::getItemMeasure($val['code']);
			}
		}
		
		// ソート
		if($priceorder!='index'){
			$sort = 'price';
		}else{
			$sort = 'row';
		}
		foreach($r as $key=>$row){
			$sortkey[$key] = $row[$sort];
		}
		if($priceorder!='high'){
			array_multisort($sortkey,SORT_ASC,SORT_NUMERIC,$r);
			//usort($data, 'sort_asc');
		}else{
			array_multisort($sortkey,SORT_DESC,SORT_NUMERIC,$r);
			//usort($data, 'sort_desc');
		}
		
		return $r;
	}
	
	
	// カテゴリ毎のシルエットで使用する絵型IDと絵型名のハッシュ
	private $silhouetteId = array('',
						array(1=>'綿素材',3=>'ドライ'),
						array(6=>'トレーナー',7=>'プルパーカー',8=>'パンツ',10=>'ジップパーカー'),
						array(12=>'ポケット無し',13=>'ポケット有り'),
						array(3=>'GAME',16=>'TRAINING'),
						array(3=>'ドライ',7=>'スウェット',41=>'綿素材'),
						array(18=>'薄い生地',23=>'厚い生地'),
						array(25=>'キャップ',32=>'バンダナ'),
						array(29=>'タオル'),
						array(30=>'トートバッグ'),
						array(27=>'肩がけ',34=>'腰巻き'),
						array(18=>'ドリズラー',40=>'パンツ'),
						array(32=>'全アイテム'),
						array(2=>'長袖',4=>'七部袖'),
						array(31=>'ベビー'),
						array(),
						array(49=>'長袖',50=>'半袖'),
					);
	
	// 各絵型のプリント位置の名称
	private $positionName = array(
		"normal-tshirts_01"=>"まえ",
		"normal-tshirts_02"=>"左胸",
		"normal-tshirts_03"=>"右胸",
		"normal-tshirts_04"=>"左裾",
		"normal-tshirts_05"=>"まえ裾",
		"normal-tshirts_06"=>"右裾",
		"normal-tshirts_07"=>"後ろ",
		"normal-tshirts_08"=>"首後ろ",
		"normal-tshirts_09"=>"後左裾",
		"normal-tshirts_10"=>"後ろ裾",
		"normal-tshirts_11"=>"後右裾",
		"normal-tshirts_12"=>"左袖",
		"normal-tshirts_13"=>"左脇",
		"normal-tshirts_14"=>"右袖",
		"normal-tshirts_15"=>"右脇",
		"long-tshirts_01"=>"まえ",
		"long-tshirts_02"=>"左胸",
		"long-tshirts_03"=>"右胸",
		"long-tshirts_04"=>"左裾",
		"long-tshirts_05"=>"まえ裾",
		"long-tshirts_06"=>"右裾",
		"long-tshirts_07"=>"後ろ",
		"long-tshirts_08"=>"首後ろ",
		"long-tshirts_09"=>"後左裾",
		"long-tshirts_10"=>"後ろ裾",
		"long-tshirts_11"=>"後右裾",
		"long-tshirts_12"=>"左腕",
		"long-tshirts_13"=>"左袖口",
		"long-tshirts_14"=>"左脇",
		"long-tshirts_15"=>"右腕",
		"long-tshirts_16"=>"右袖口",
		"long-tshirts_17"=>"右脇",
		"raglan-tshirts_01"=>"まえ",
		"raglan-tshirts_02"=>"左胸",
		"raglan-tshirts_03"=>"右胸",
		"raglan-tshirts_04"=>"左裾",
		"raglan-tshirts_05"=>"まえ裾",
		"raglan-tshirts_06"=>"右裾",
		"raglan-tshirts_07"=>"後ろ",
		"raglan-tshirts_08"=>"首後ろ",
		"raglan-tshirts_09"=>"後左裾",
		"raglan-tshirts_10"=>"後ろ裾",
		"raglan-tshirts_11"=>"後右裾",
		"raglan-tshirts_12"=>"左袖",
		"raglan-tshirts_13"=>"左脇",
		"raglan-tshirts_14"=>"右袖",
		"raglan-tshirts_15"=>"右脇",
		"raglan-long-tshirts_01"=>"まえ",
		"raglan-long-tshirts_02"=>"左胸",
		"raglan-long-tshirts_03"=>"右胸",
		"raglan-long-tshirts_04"=>"左裾",
		"raglan-long-tshirts_05"=>"まえ裾",
		"raglan-long-tshirts_06"=>"右裾",
		"raglan-long-tshirts_07"=>"後ろ",
		"raglan-long-tshirts_08"=>"首後ろ",
		"raglan-long-tshirts_09"=>"後左裾",
		"raglan-long-tshirts_10"=>"後ろ裾",
		"raglan-long-tshirts_11"=>"後右裾",
		"raglan-long-tshirts_12"=>"左腕",
		"raglan-long-tshirts_13"=>"左袖口",
		"raglan-long-tshirts_14"=>"左脇",
		"raglan-long-tshirts_15"=>"右腕",
		"raglan-long-tshirts_16"=>"右袖口",
		"raglan-long-tshirts_17"=>"右脇",
		"tanktop_01"=>"まえ",
		"tanktop_02"=>"左胸",
		"tanktop_03"=>"右胸",
		"tanktop_04"=>"左裾",
		"tanktop_05"=>"まえ裾",
		"tanktop_06"=>"右裾",
		"tanktop_07"=>"後ろ",
		"tanktop_08"=>"首後ろ",
		"tanktop_09"=>"左裾",
		"tanktop_10"=>"後ろ裾",
		"tanktop_11"=>"右裾",
		"tanktop_12"=>"左脇",
		"tanktop_13"=>"右脇",
		"trainer_01"=>"まえ",
		"trainer_02"=>"左胸",
		"trainer_03"=>"右胸",
		"trainer_04"=>"左裾",
		"trainer_05"=>"まえ裾",
		"trainer_06"=>"右裾",
		"trainer_07"=>"後ろ",
		"trainer_08"=>"首後ろ",
		"trainer_09"=>"後左裾",
		"trainer_10"=>"後ろ裾",
		"trainer_11"=>"後右裾",
		"trainer_12"=>"左腕",
		"trainer_13"=>"左袖口",
		"trainer_14"=>"右腕",
		"trainer_15"=>"右袖口",
		"parker_01"=>"まえ",
		"parker_02"=>"左胸",
		"parker_03"=>"右胸",
		"parker_04"=>"前フード",
		"parker_05"=>"後ろ",
		"parker_06"=>"後左裾",
		"parker_07"=>"後ろ裾",
		"parker_08"=>"後右裾",
		"parker_09"=>"左腕",
		"parker_10"=>"左フード",
		"parker_11"=>"右腕",
		"parker_12"=>"右フード",
		"long-pants_01"=>"左前",
		"long-pants_02"=>"左もも前",
		"long-pants_03"=>"左足前",
		"long-pants_04"=>"右前",
		"long-pants_05"=>"右もも前",
		"long-pants_06"=>"右足前",
		"long-pants_07"=>"左後",
		"long-pants_08"=>"左もも後",
		"long-pants_09"=>"左足後",
		"long-pants_10"=>"右後",
		"long-pants_11"=>"右もも後",
		"long-pants_12"=>"右足後",
		"short-pants_01"=>"左前裾",
		"short-pants_02"=>"右前裾",
		"short-pants_03"=>"左後",
		"short-pants_04"=>"右後",
		"short-pants_05"=>"左後裾",
		"short-pants_06"=>"右後裾",
		"zip-parker_01"=>"左胸",
		"zip-parker_02"=>"右胸",
		"zip-parker_03"=>"前フード",
		"zip-parker_04"=>"後ろ",
		"zip-parker_05"=>"後左裾",
		"zip-parker_06"=>"後ろ裾",
		"zip-parker_07"=>"後右裾",
		"zip-parker_08"=>"左腕",
		"zip-parker_09"=>"左フード",
		"zip-parker_10"=>"右腕",
		"zip-parker_11"=>"右フード",
		"zip-jacket_01"=>"左胸",
		"zip-jacket_02"=>"右胸",
		"zip-jacket_03"=>"後ろ",
		"zip-jacket_04"=>"首後ろ",
		"zip-jacket_05"=>"後左裾",
		"zip-jacket_06"=>"後ろ裾",
		"zip-jacket_07"=>"後右裾",
		"zip-jacket_08"=>"左腕",
		"zip-jacket_09"=>"左袖口",
		"zip-jacket_10"=>"右腕",
		"zip-jacket_11"=>"右袖口",
		"polo-non-pocket_01"=>"左胸",
		"polo-non-pocket_02"=>"右胸",
		"polo-non-pocket_03"=>"まえ",
		"polo-non-pocket_04"=>"左裾",
		"polo-non-pocket_05"=>"まえ裾",
		"polo-non-pocket_06"=>"右裾",
		"polo-non-pocket_07"=>"後ろ",
		"polo-non-pocket_08"=>"首後ろ",
		"polo-non-pocket_09"=>"後左裾",
		"polo-non-pocket_10"=>"後ろ裾",
		"polo-non-pocket_11"=>"後右裾",
		"polo-non-pocket_12"=>"左袖",
		"polo-non-pocket_13"=>"右袖",
		"polo-with-pocket_01"=>"ポケ上",
		"polo-with-pocket_02"=>"ポケット",
		"polo-with-pocket_03"=>"右胸",
		"polo-with-pocket_04"=>"まえ",
		"polo-with-pocket_05"=>"左裾",
		"polo-with-pocket_06"=>"まえ裾",
		"polo-with-pocket_07"=>"右裾",
		"polo-with-pocket_08"=>"後ろ",
		"polo-with-pocket_09"=>"首後ろ",
		"polo-with-pocket_10"=>"後左裾",
		"polo-with-pocket_11"=>"後ろ裾",
		"polo-with-pocket_12"=>"後右裾",
		"polo-with-pocket_13"=>"左袖",
		"polo-with-pocket_14"=>"右袖",
		"longpolo-non-pocket_01"=>"左胸",
		"longpolo-non-pocket_02"=>"右胸",
		"longpolo-non-pocket_03"=>"まえ",
		"longpolo-non-pocket_04"=>"左裾",
		"longpolo-non-pocket_05"=>"まえ裾",
		"longpolo-non-pocket_06"=>"右裾",
		"longpolo-non-pocket_07"=>"後ろ",
		"longpolo-non-pocket_08"=>"首後ろ",
		"longpolo-non-pocket_09"=>"後左裾",
		"longpolo-non-pocket_10"=>"後ろ裾",
		"longpolo-non-pocket_11"=>"後右裾",
		"longpolo-non-pocket_12"=>"左腕",
		"longpolo-non-pocket_13"=>"左袖口",
		"longpolo-non-pocket_14"=>"右腕",
		"longpolo-non-pocket_15"=>"右袖口",
		"longpolo-with-pocket_01"=>"ポケ上",
		"longpolo-with-pocket_02"=>"ポケット",
		"longpolo-with-pocket_03"=>"右胸",
		"longpolo-with-pocket_04"=>"まえ",
		"longpolo-with-pocket_05"=>"左裾",
		"longpolo-with-pocket_06"=>"まえ裾",
		"longpolo-with-pocket_07"=>"右裾",
		"longpolo-with-pocket_08"=>"後ろ",
		"longpolo-with-pocket_09"=>"首後ろ",
		"longpolo-with-pocket_10"=>"後左裾",
		"longpolo-with-pocket_11"=>"後ろ裾",
		"longpolo-with-pocket_12"=>"後右裾",
		"longpolo-with-pocket_13"=>"左腕",
		"longpolo-with-pocket_14"=>"左袖口",
		"longpolo-with-pocket_15"=>"右腕",
		"longpolo-with-pocket_16"=>"右袖口",
		"jacket',2_01"=>"左胸",
		"jacket',2_02"=>"右胸",
		"jacket',2_03"=>"後ろ",
		"long-pants',2_01"=>"左前",
		"long-pants',2_02"=>"左もも前",
		"long-pants',2_03"=>"左足前",
		"long-pants',2_04"=>"右前",
		"long-pants',2_05"=>"右もも前",
		"long-pants',2_06"=>"右足前",
		"long-pants',2_07"=>"左後",
		"long-pants',2_08"=>"左もも後",
		"long-pants',2_09"=>"左足後",
		"long-pants',2_10"=>"右後",
		"long-pants',2_11"=>"右もも後",
		"long-pants',2_12"=>"右足後",
		"blouson_01"=>"左胸",
		"blouson_02"=>"右胸",
		"blouson_03"=>"後ろ",
		"blouson_04"=>"左腕",
		"blouson_05"=>"右腕",
		"coat_01"=>"左胸",
		"coat_02"=>"右胸",
		"coat_03"=>"後ろ",
		"coat_04"=>"左腕",
		"coat_05"=>"右腕",
		"bench-coat_01"=>"左胸",
		"bench-coat_02"=>"右胸",
		"bench-coat_03"=>"後ろ",
		"bench-coat_04"=>"左腕",
		"bench-coat_05"=>"右腕",
		"best',2_01"=>"左胸",
		"best',2_02"=>"右胸",
		"best',2_03"=>"後ろ",
		"outdoor-jacket_01"=>"左胸",
		"outdoor-jacket_02"=>"右胸",
		"outdoor-jacket_03"=>"後ろ",
		"outdoor-jacket_04"=>"左腕",
		"outdoor-jacket_05"=>"右腕",
		"sports-jacket_01"=>"左胸",
		"sports-jacket_02"=>"右胸",
		"sports-jacket_03"=>"後ろ",
		"sports-jacket_04"=>"左腕",
		"sports-jacket_05"=>"右腕",
		"windbreaker_01"=>"左胸",
		"windbreaker_02"=>"右胸",
		"windbreaker_03"=>"後ろ",
		"windbreaker_04"=>"左腕",
		"windbreaker_05"=>"右腕",
		"mesh-cap_01"=>"まえ",
		"twill-cap_01"=>"左まえ",
		"twill-cap_02"=>"右まえ",
		"apron_01"=>"まえ",
		"apron_02"=>"ポケ中",
		"apron_03"=>"左裾",
		"happi_01"=>"右袖",
		"happi_02"=>"右胸",
		"happi_03"=>"前たて右",
		"happi_04"=>"左袖",
		"happi_05"=>"左胸",
		"happi_06"=>"前たて左",
		"happi_07"=>"後ろ",
		"towel_01"=>"中央",
		"towel_02"=>"サイド",
		"bag_01"=>"前面",
		"bag_02"=>"後面",
		"rompers_01"=>"まえ",
		"rompers_02"=>"左胸",
		"rompers_03"=>"右胸",
		"rompers_04"=>"後ろ",
		"rompers_05"=>"首後ろ",
		"rompers_06"=>"左袖",
		"rompers_07"=>"右袖",
		"visor_01"=>"中央",
		"visor_01"=>"中央",
		"short-apron_01"=>"まえ",
		"short-apron_02"=>"ポケ中",
		"short-apron_03"=>"左裾",
		"mascot-tshirts_01"=>"まえ",
		"mascot-tshirts_02"=>"後ろ",
		"pocket-tshirts_01"=>"ポケ上",
		"pocket-tshirts_02"=>"ポケ中",
		"pocket-tshirts_03"=>"右胸",
		"pocket-tshirts_04"=>"まえ",
		"pocket-tshirts_05"=>"左裾",
		"pocket-tshirts_06"=>"まえ裾",
		"pocket-tshirts_07"=>"右裾",
		"pocket-tshirts_08"=>"後ろ",
		"pocket-tshirts_09"=>"首後ろ",
		"pocket-tshirts_10"=>"後左裾",
		"pocket-tshirts_11"=>"後ろ裾",
		"pocket-tshirts_12"=>"後右裾",
		"pocket-tshirts_13"=>"左袖",
		"pocket-tshirts_14"=>"左脇",
		"pocket-tshirts_15"=>"右袖",
		"pocket-tshirts_16"=>"右脇",
		"boxerpants_01"=>"右まえ",
		"boxerpants_02"=>"左まえ",
		"boxerpants_03"=>"後ろ",
		"army-work-cap_01"=>"まえ",
		"active-dry-cap_01"=>"まえ",
		"chino-pants_01"=>"左前",
		"chino-pants_02"=>"左もも前",
		"chino-pants_03"=>"左足前",
		"chino-pants_04"=>"右前",
		"chino-pants_05"=>"右もも前",
		"chino-pants_06"=>"右足前",
		"chino-pants_07"=>"左後",
		"chino-pants_08"=>"左もも後",
		"chino-pants_09"=>"左足後",
		"chino-pants_10"=>"右後",
		"chino-pants_11"=>"右もも後",
		"chino-pants_12"=>"右足後",
		"fraise-t_01"=>"まえ",
		"fraise-t_02"=>"左胸",
		"fraise-t_03"=>"右胸",
		"fraise-t_04"=>"左裾",
		"fraise-t_05"=>"まえ裾",
		"fraise-t_06"=>"右裾",
		"fraise-t_07"=>"後ろ",
		"fraise-t_08"=>"首後ろ",
		"fraise-t_09"=>"後左裾",
		"fraise-t_10"=>"後ろ裾",
		"fraise-t_11"=>"後右裾",
		"fraise-t_12"=>"左袖",
		"fraise-t_13"=>"左脇",
		"fraise-t_14"=>"右袖",
		"fraise-t_15"=>"右脇",
		"henry-neck-t_01"=>"左胸",
		"henry-neck-t_02"=>"右胸",
		"henry-neck-t_03"=>"まえ",
		"henry-neck-t_04"=>"左裾",
		"henry-neck-t_05"=>"まえ裾",
		"henry-neck-t_06"=>"右裾",
		"henry-neck-t_07"=>"後ろ",
		"henry-neck-t_08"=>"首後ろ",
		"henry-neck-t_09"=>"後左裾",
		"henry-neck-t_10"=>"後ろ裾",
		"henry-neck-t_11"=>"後右裾",
		"henry-neck-t_12"=>"左袖",
		"henry-neck-t_13"=>"左脇",
		"henry-neck-t_14"=>"右袖",
		"henry-neck-t_15"=>"右脇",
		"button-down-shirt-short_01"=>"ポケ上",
		"button-down-shirt-short_02"=>"ポケット",
		"button-down-shirt-short_03"=>"右胸",
		"button-down-shirt-short_04"=>"左裾",
		"button-down-shirt-short_05"=>"右裾",
		"button-down-shirt-short_06"=>"後ろ",
		"button-down-shirt-short_07"=>"首後ろ",
		"button-down-shirt-short_08"=>"後左裾",
		"button-down-shirt-short_09"=>"後ろ裾",
		"button-down-shirt-short_10"=>"後右裾",
		"button-down-shirt-short_11"=>"左腕",
		"button-down-shirt-short_12"=>"右腕",
		"button-down-shirt-short_01"=>"ポケ上",
		"button-down-shirt-short_02"=>"ポケット",
		"button-down-shirt-short_03"=>"右胸",
		"button-down-shirt-short_04"=>"左裾",
		"button-down-shirt-short_05"=>"右裾",
		"button-down-shirt-short_06"=>"後ろ",
		"button-down-shirt-short_07"=>"首後ろ",
		"button-down-shirt-short_08"=>"後左裾",
		"button-down-shirt-short_09"=>"後ろ裾",
		"button-down-shirt-short_10"=>"後右裾",
		"button-down-shirt-short_11"=>"左袖",
		"button-down-shirt-short_12"=>"右袖",
		"polyester-pants_01"=>"左前",
		"polyester-pants_02"=>"左もも前",
		"polyester-pants_03"=>"左足前",
		"polyester-pants_04"=>"右前",
		"polyester-pants_05"=>"右もも前",
		"polyester-pants_06"=>"右足前",
		"polyester-pants_07"=>"左もも後",
		"polyester-pants_08"=>"左足後",
		"polyester-pants_09"=>"右後",
		"polyester-pants_10"=>"右もも後",
		"polyester-pants_11"=>"右足後",
		"noprint_01"=>"なし",
		"parker-non-hood_01"=>"まえ",
		"parker-non-hood_02"=>"左胸",
		"parker-non-hood_03"=>"右胸",
		"parker-non-hood_04"=>"後ろ",
		"parker-non-hood_05"=>"後左裾",
		"parker-non-hood_06"=>"後ろ裾",
		"parker-non-hood_07"=>"後右裾",
		"parker-non-hood_08"=>"左腕",
		"parker-non-hood_09"=>"左フード",
		"parker-non-hood_10"=>"右腕",
		"parker-non-hood_11"=>"右フード",
		"zip-parker-non-hood_01"=>"左胸",
		"zip-parker-non-hood_02"=>"右胸",
		"zip-parker-non-hood_03"=>"後ろ",
		"zip-parker-non-hood_04"=>"後左裾",
		"zip-parker-non-hood_05"=>"後ろ裾",
		"zip-parker-non-hood_06"=>"後右裾",
		"zip-parker-non-hood_07"=>"左腕",
		"zip-parker-non-hood_08"=>"左フード",
		"zip-parker-non-hood_09"=>"右腕",
		"zip-parker-non-hood_10"=>"右フード",
		"tsunagi_01"=>"左胸",
		"tsunagi_02"=>"右胸",
		"tsunagi_03"=>"後ろ",
		"tsunagi-short_01"=>"左胸",
		"tsunagi-short_02"=>"右胸",
		"tsunagi-short_03"=>"後ろ",
		"tsunagi-back_01"=>"後ろ",
		"basket-shirt_01"=>"まえ",
		"basket-shirt_02"=>"後ろ",
		"game-pants_01"=>"左まえ",
		"game-pants_02"=>"右まえ",
		"game-pants_03"=>"左後ろ",
		"game-pants_04"=>"右後ろ",
	);
	
	
	// 評価を0.5単位に変換し画像パスを返す
	private function getStar($args){
		if($args<0.5){
			$r = 'star00';
		}else if($args>=0.5 && $args<1){
			$r = 'star05';
		}else if($args>=1 && $args<1.5){
			$r = 'star10';
		}else if($args>=1.5 && $args<2){
			$r = 'star15';
		}else if($args>=2 && $args<2.5){
			$r = 'star20';
		}else if($args>=2.5 && $args<3){
			$r = 'star25';
		}else if($args>=3 && $args<3.5){
			$r = 'star30';
		}else if($args>=3.5 && $args<4){
			$r = 'star35';
		}else if($args>=4 && $args<4.5){
			$r = 'star40';
		}else if($args>=4.5 && $args<5){
			$r = 'star45';
		}else{
			$r = 'star50';
		}
		return $r;
	}
	
	
	/*
	* プリントポジションIDを返す
	* @id	category ID
	*/
	public function getPrintPositionID($id){
		return $this->silhouetteId[$id];
	}
	
	
	/*
	* 見積ページのシルエットのタグを返す
	* @id		category ID
	*/
	public function getSilhouette($id){
		$idx = 1;
		foreach($this->silhouetteId[$id] as $ppid=>$lbl){
			$files = parent::positionFor($ppid, 'pos');
			$imgfile = file_get_contents($files[0]['filename']);
			$f = preg_replace('/.\/img\//', _IMG_PSS, $imgfile);
			preg_match('/<img (.*?)>/', $f, $match);
			//$f = mb_convert_encoding($match[1], 'euc-jp', 'utf-8');
			$box .= '<li>';
				$box .= '<div class="back">';
					$box .= '<span class="heightLine-1"><img '.$match[1].'>'.$lbl.'</span>';
					$box .= '<input type="radio" value="'.$ppid.'" name="body_type" class="check_body" id="check'.$idx.'"';
					if($idx==1) $box .= ' checked="checked"';
					$box .= '><label for="check'.$idx.'">&nbsp;</label>';
				$box .= '</div>';
			$box .= '</li>';
			
			$idx++;
		}
		
		return $box;
	}
	
	
	/*
	* 見積ページのプリント位置指定のタグを返す
	* @id		printposition ID
	* @offset	インデックスの開始番号
	*/
	public function getPrintPosition($id, $offset=0){
		if(preg_match('/\A[1-9][0-9]*\z/', $id)){
			$files = parent::positionFor($id, 'pos');
		}else{
			return;
		}
		
		//$files = parent::positionFor($args, 'pos');
		$filedir = "/m3/img/position/".$files[0]['ppdata']['category']."/".$files[0]['ppdata']['item']."/";
		$path = dirname(__FILE__)."/../..".$filedir."*.png";
		foreach (glob($path) as $filename) {
			$base = basename($filename, '.png');
			$posName = $this->positionName[$base];
			$tmp = explode("_", $base);
			$num = $tmp[1]."-".$offset;
			$pos .= '<li class="swiper-slide">';
				$pos .= '<span><img src="'.$filedir.$base.'.png" width="85" height="74" alt="">'.$posName.'</span>';
				$pos .= '<input type="checkbox" name="check'.$num.'" value="'.$posName.'" class="check_pos" id="check'.$num.'">';
				$pos .= '<label for="check'.$num.'">&nbsp;</label>';
			$pos .= '</li>';
		}
		
		return $pos;
	}
	
	
	/* 
	*	絵型の表示をソート(public)
	*	order by printposition_id, selective_key
	*/
	public function sortSelectivekey($args){
		$tmp = array(
			"mae"=>1,
			"mae_mini"=>1,
			"jacket_mae_mini"=>1,
			"mae_mini_2"=>1,
			"parker_mae_mini_2"=>1,
			"parker_mae_mini_zip "=>1,
			"apron_mae"=>1,
			"tote_mae"=>1,
			"short_apron_mae"=>1,
			"cap_mae"=>1,
			"visor_mae "=>1,
			"active_mae"=>1,
			"army_mae"=>1,
			
			"mae_hood"=>2,
			"short_apron_ue"=>2,
			
			"mune_right"=>3,
			"parker_mune_right"=>3,
			"active_mune_right"=>3,
			"cap_mae_right"=>3,
			"boxerpants_right"=>3,
			"shirt_mune_right"=>3,
			"game_pants_suso_right"=>3,
			
			"pocket"=>4,
			"parker_mae_pocket"=>4,
			"apron_pocket"=>4,
			"short_apron_pocket"=>4,
			
			"mune_left"=>5,
			"parker_mune_left"=>5,
			"active_mune_left"=>5,
			"polo_mune_left"=>5,
			"cap_mae_left"=>5,
			"boxerpants_left"=>5,
			"game_pants_suso_left"=>5,
			
			"suso_left"=>6,
			"apron_suso_left"=>6,
			"shirt_suso_left"=>6,
			
			"suso_mae"=>7,
			
			"suso_right"=>8,
			"shirt_suso_right"=>8,
			
			
			"mae_right"=>9,
			"workwear_mae_right"=>9,
			
			"mae_suso_right"=>10,
			"boxerpants_suso_right"=>10,
			
			"mae_momo_right"=>11,
			"workwear_mae_momo_right"=>11,
			
			"mae_hiza_right"=>12,
			"workwear_mae_hiza_right"=>12,
			
			"mae_asi_right"=>13,
			"workwear_mae_asi_right"=>13,
			
			
			"mae_left"=>14,
			"workwear_mae_left"=>14,
			
			"mae_suso_left"=>15,
			"boxerpants_suso_left"=>15,
			
			"mae_momo_left"=>16,
			"workwear_mae_momo_left"=>16,
			
			"mae_hiza_left"=>17,
			"workwear_mae_hiza_left"=>17,
			
			"mae_asi_left"=>18,
			"workwear_mae_asi_left"=>18,
			
			"happi_sode_left"=>19,
			"happi_mune_left"=>19,
			"happi_maetate_left"=>19,
			"happi_sode_right"=>19,
			"happi_mune_right"=>19,
			"happi_maetate_right"=>19,
			
			"towel_center"=>20,
			"towel_left"=>20,
			"towel_right"=>20,
			
			
			
			"usiro"=>21,
			"usiro_mini"=>21,
			"parker_usiro"=>21,
			"bench_usiro"=>21,
			"best_usiro"=>21,
			"tote_usiro"=>21,
			"cap_usiro"=>21,
			"active_cap_usiro"=>21,
			
			"eri"=>22,
			"kubi_usiro"=>22,
			"shirt_long_kubi_usiro"=>22,
			"shirt_short_kubi_usiro"=>22,
			
			"usiro_suso_left"=>23,
			"shirt_usiro_suso_left"=>23,
			
			"usiro_suso"=>24,
			
			"usiro_suso_right"=>25,
			"shirt_usiro_suso_right"=>25,
			
			"osiri"=>26,
			"pants_osiri"=>26,
			"boxerpants_osiri"=>26,
			
			
			"usiro_left"=>27,
			"pants_usiro_left"=>27,
			"workwear_usiro_left"=>27,
			
			"pants_usiro_suso_left"=>28,
			"boxerpants_usiro_suso_left"=>28,
			"game_pants_usiro_suso_left"=>28,
			
			"usiro_momo_left"=>29,
			"workwear_usiro_momo_left"=>29,
			
			"usiro_hiza_left"=>30,
			"workwear_usiro_hiza_left"=>30,
			
			"usiro_asi_left"=>31,
			"workwear_usiro_asi_left"=>31,
			
			"usiro_right"=>32,
			"pants_usiro_right"=>32,
			"workwear_usiro_right"=>32,
			
			"pants_usiro_suso_right"=>33,
			"boxerpants_usiro_suso_right"=>33,
			"game_pants_usiro_suso_right"=>33,
			
			"usiro_momo_right"=>34,
			"workwear_usiro_momo_right"=>34,
			
			"usiro_hiza_right"=>35,
			"workwear_usiro_hiza_right"=>35,
			
			"usiro_asi_right"=>36,
			"workwear_usiro_asi_right"=>36,
			
			
			
			"sode_right"=>37,
			"sode_right2"=>37,
			
			"hood_right"=>38,
			
			"long_sode_right"=>39,
			"trainer_sode_right"=>39,
			"parker_sode_right"=>39,
			"blouson_sode_right"=>39,
			"coat_sode_right"=>39,
			"boxerpants_side_right"=>39,
			"shirt_sode_right"=>39,
			"shirt_long_sode_right"=>39,
			
			"long_ude_right"=>40,
			"trainer_ude_right"=>40,
			"parker_ude_right"=>40,
			"blouson_ude_right"=>40,
			"coat_ude_right"=>40,
			"shirt_long_ude_right"=>40,
			
			"long_sodeguti_right"=>41,
			"trainer_sodeguti_right"=>41,
			
			"long_waki_right"=>42,
			"waki_right"=>42,
			"waki_right2"=>42,
			
			"sode_left"=>43,
			"sode_left2"=>43,
			
			"hood_left"=>44,
			
			"long_sode_left"=>45,
			"trainer_sode_left"=>45,
			"parker_sode_left"=>45,
			"blouson_sode_left"=>45,
			"coat_sode_left"=>45,
			"boxerpants_side_left"=>45,
			"shirt_sode_left"=>45,
			"shirt_long_sode_left"=>45,
			
			"long_ude_left"=>46,
			"trainer_ude_left"=>46,
			"parker_ude_left"=>46,
			"blouson_ude_left"=>46,
			"coat_ude_left"=>46,
			"shirt_long_ude_left"=>46,
			
			"long_sodeguti_left"=>47,
			"trainer_sodeguti_left"=>47,
			
			"long_waki_left"=>48,
			"waki_left"=>48,
			"waki_left2"=>48,
			
			"cap_side_right"=>49,
			"active_cap_side_right"=>49,
			
			"cap_side_left"=>50,
			"active_cap_side_left"=>50
		);
		
		foreach($args as $key=>$val){
			$a[$key] = $tmp[$val['key']];
		}
		array_multisort($a, $args);
		
		return $args;
	}
}


if(isset($_REQUEST['act'])){
	$iteminfo = new ItemInfo();
	switch($_REQUEST['act']){
	case 'body':
		// item silhouette
		$res = $iteminfo->getSilhouette($_REQUEST['category_id']);
		break;
		
	case 'position':
		// print position
		$res = $iteminfo->getPrintPosition($_REQUEST['pos_id']);
		break;
	}
	
	echo $res;
}
?>