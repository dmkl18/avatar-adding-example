<?php

class ImageException extends Exception{

    public function __construct($mes){
        parent::__construct($mes);
    }

}

class Image{

    const NOTIMAGE = "Указанный Вами фаил не является изображением";
    const WRONGSIZE = "Размеры файла не соответствуют допустимому значениею - не более 3М"; //тут внимательно возможно изменение цифры
    const IMAGEMISTAKE = "При загрузке файла произошла ошибка. Попытайтесь загрузить файл снова.";
    const NOTIMAGESIZE = "При изменении размеров изображения Вы не указали или указали неправильные размеры";

    private $imname = null;
    private $maxsize = 3048000;
    private $path = 'images/';
    private $quality = 75; // может быть от 0 до 100
    private $imagesName = array(); //массив с картинками
    private $currentImage; //текущая картинка

    public function __construct(array $settings=null){
        if($settings[0]) $this->maxsize = (int) $settings[0];
        if($settings[1]) $this->path = $settings[1];
        if($settings[2]) $this->quality = (int) $settings[2];
        if($settings[3]) $this->imname = (int) $settings[3];
    }

    //улучшить защиту
    public function setImage(array $image){
        if($this->checkImage($image)){
            $target = ($this->imname) ? time().$this->imname : time().basename($image['name']);
            $targetAbs = $this->path.$target;
            move_uploaded_file($image['tmp_name'], $targetAbs); //сохраняем файл в постоянном каталоге
            $this->imagesName[basename($image['name'])] = $target; //сохраняем имя файла в массиве, а в качестве ключа - первоначальное имя файла
            return $target;
        }
    }

    public function getCurrentImage()
    {
        return $this->currentImage;
    }

    //$imageName - это строка с именем, если файл уже загружен или массив, если файл еще не был загружен и нуждается в предварительной проверке
    //$ne - false означает, что мы работаем с уже загруженным файлом
    public function resize($imageName, $width, $height, $prefix='', $path=null, $ne = false){
        $data = ($ne) ? $this->getOriginalImageDataNew($imageName, $width, $height) : $this->getOriginalImageData($imageName, $width, $height, $path);
        return $this->createNewImage($data[0], $data[1], array(0, 0), $prefix);
    }

    //высота формируется пропорционально
    public function resizeWidth($imageName, $width, $prefix='', $path=null, $ne = false){
        if(!$width)
            throw new ImageException(self::NOTIMAGESIZE);
        return $this->resize($imageName, $width, false, $prefix, $path, $ne);
    }

    //ширина формируется пропорционально
    public function resizeHeight($imageName, $height, $prefix='', $path=null, $ne = false){
        if(!$height)
            throw new ImageException(self::NOTIMAGESIZE);
        return $this->resize($imageName, false, $height, $prefix, $path, $ne);
    }

    //используется для обрезки изображения
    public function crop($imageName, $x=0, $y=0, $width, $height, $prefix='', $path=null, $ne = false){
        $data = ($ne) ? $this->getOriginalImageDataNew($imageName, $width, $height) : $this->getOriginalImageData($imageName, $width, $height, $path);
        if(($x + $data[1][0]) > $data[0][0])
            $data[1][0] = $data[0][0] - $x; //если была передана ширина, превышающая ширину изображения с учетом точки отсчета, то присваиваем ей просто значение ширины изображения минус точка отсчета
        if(($y + $data[1][1]) > $data[0][1])
            $data[1][1] = $data[0][1] - $y; //тоже самое
        return $this->createNewImage(array($data[1][0], $data[1][1], $data[0][2], $data[0][3]), $data[1], array($x, $y), $prefix);
    }

    //вырезается именно центральная часть изображения
    public function cropCenter($imageName, $width, $height, $prefix='', $path=null, $ne = false){
        $data = ($ne) ? $this->getOriginalImageDataNew($imageName, $width, $height) : $this->getOriginalImageData($imageName, $width, $height, $path);
        if($data[1][0] > $data[0][0])
            $data[1][0] = $data[0][0]; //если ширина вырезаемой части превысила исходную, протсо присваиваем ширину исходного изображения
        if($data[1][1] > $data[0][1])
            $data[1][1] = $data[0][1]; //тоже самое для высоты
        $x = ($data[0][0] - $data[1][0])/2; //вычисляем x y. чтобы изображение было по центру
        $y = ($data[0][1] - $data[1][1])/2;
        return $this->createNewImage(array($data[1][0], $data[1][1], $data[0][2], $data[0][3]), $data[1], array($x, $y), $prefix);
    }

    //подходит для создания миниатюр в галереи одинакового размера
    //изображения сжимается до указанных размеров, при этом сохраняется соотношение пропорций изображения, для чего часть его может обрезаться
    public function exactDimensionsImage($imageName, $width, $height, $prefix='', $path=null, $ne = false){
        $data = ($ne) ? $this->getOriginalImageDataNew($imageName, $width, $height) : $this->getOriginalImageData($imageName, $width, $height, $path);
        $kw = $data[0][0] / $data[1][0]; //вычисляем коэффициент для высоты и ширины
        $kh = $data[0][1] / $data[1][1];
        $x = 0;
        $y = 0;
        if($data[1][0] * $kh > $data[0][0]){ //если необходимо, то через коэффициеты вычиляем новые значения высоты и ширины, а также центрируем вырезаемую часть.
            $y = ($data[0][1] - $data[1][1] * $kw) / 2;
            $data[0][1] = $data[1][1] * $kw;
        }
        else if($data[1][1] * $kw > $data[0][1]){
            $x = ($data[0][0] - $data[1][0] * $kh) / 2;
            $data[0][0] = $data[1][0] * $kh;
        }
        return $this->createNewImage($data[0], $data[1], array($x, $y), $prefix);
    }

    private function checkImage(array $image){
        $blacklist = array("php", "phtml", "php3", "php4", "html", "htm"); //сперва проверяем, чтобы это не был php файл
        $im_r = array_slice(explode('.', $image['name']),1);
        foreach($im_r as $imr){
            if(in_array($imr, $blacklist))
                throw new ImageException(self::NOTIMAGE);
        }
        if(!$this->checkType($image['type']))
            throw new ImageException(self::NOTIMAGE); //проверяем тип изображения
        if($image['size'] < 0 || $image['size'] >= $this->maxsize)
            throw new ImageException(self::WRONGSIZE); //размер
        if($image['error'] != 0)
            throw new ImageException(self::IMAGEMISTAKE);
        $this->checkTypeByGS($image['tmp_name']); //и еще раз тип на основе файла, находящегося во временном каталоге
        return true;
    }

    private function checkType($type){
        if(($type != 'image/gif') && ($type != 'image/jpeg') && ($type != 'image/jpg') && ($type != 'image/png')) return false;
        return true;
    }

    private function checkTypeByGS($image){
        list($w_i, $h_i, $type) = getimagesize($image);
        $types = array("", "gif", "jpeg", "png");
        $ext = $types[$type];
        if (!$ext)
            throw new Exception(Constants::UNKNOWNERROR);
        return array($w_i, $h_i, $ext);
    }

    // $imageName - это массив FILES для конкретного изображения
    private function getOriginalImageDataNew(array $imageName, $width, $height){ //для файлов, которые не были загружены
        if($this->checkImage($imageName)){
            $this->currentImage = time().$imageName['name'];
            return $this->getFullImageData($imageName['tmp_name'], $width, $height); //на основе файла во временном каталоге получаем ресурс файла, его размеры и тип
        }
    }

    // $imageName - имя просто загруженного файла
    private function getOriginalImageData($imageName, $width, $height, $path=null){ //для файлов, которые уже были загружены
        $this->currentImage = ($this->imagesName[$imageName]) ? $this->imagesName[$imageName] : $imageName;
        $this->path = ($path===null) ? $this->path : $path;
        return $this->getFullImageData($this->path.$this->currentImage, $width, $height);
    }

    private function getFullImageData($image, $width, $height){
        if(!is_int($width) && !is_int($height))
            throw new ImageException(self::NOTIMAGESIZE);
        $imageData = $this->checkTypeByGS($image);
        $func = 'imagecreatefrom'.$imageData[2];
        $img_i = $func($image);
        if (!$height) $height = $width / ($imageData[0] / $imageData[1]); //если не задана высота, формируем ее прпорционально
        if (!$width) $width = $height / ($imageData[1] / $imageData[0]); //если не задана ширина, формируем ее пропорционально
        return array(array($imageData[0], $imageData[1], $img_i, $imageData[2]), array($width, $height));
        //возвращаем массив, где в качестве первого параметра возвращается информация об исходном файле, а в качестве второго
        //массив, содержащий ширину и высоту нового изображения
    }

    private function createNewImage(array $origImData, array $wh, array $topLeft, $prefix=''){
        $img_o = imagecreatetruecolor($wh[0], $wh[1]);
        imagecopyresampled($img_o, $origImData[2], 0, 0, $topLeft[0], $topLeft[1], $wh[0], $wh[1], $origImData[0], $origImData[1]);
        $func = 'image'.$origImData[3];
        $quality = ($wh[0] >= 800 || $wh[1] >= 800) ? 100 : $this->quality;
        return ($origImData[3] == 'png') ? $func($img_o, $this->path.$prefix.$this->currentImage) : $func($img_o, $this->path.$prefix.$this->currentImage, $quality);
    }

}

?>