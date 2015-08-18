<?php

require("Image.php");

$headers = getallheaders();

    try{
        $image = new Image(array(2*1024*1024, '../images/', '', mb_strlen($_FILES['avatar']['name']) > 50 ? mb_substr(sha1(mt_rand()), 0, 35) : null));
        if(!$image->exactDimensionsImage($_FILES['avatar'], 60, 60, '', null, true))
            throw new Exception(Image::IMAGEMISTAKE);
        $imageName = $image->getCurrentImage();
        $arr = array('message' => 'Изображение загружено', 'fileName' => $imageName);
        $headers["X-Requested-With"] == "XMLHttpRequest" ? print(json_encode($arr)) : print($arr["message"]);
    }
    catch(ImageException $ie){
        $exMes = $ie->getMessage();
        $arr = array('mistake' => $exMes);
        $headers["X-Requested-With"] == "XMLHttpRequest" ? print(json_encode($arr)) : print($arr["mistake"]);
    }
    catch(Exception $e){
        $exMes = $e->getMessage();
        $arr = array('mistake' => $exMes);
        $headers["X-Requested-With"] == "XMLHttpRequest" ? print(json_encode($arr)) : print($arr["mistake"]);
    }

?>