<?php
namespace frontend\controllers;

use Yii;

use yii\web\Controller;

use yii\helpers\ArrayHelper;
use yii\helpers\BaseHtmlPurifier;
use yii\helpers\HtmlPurifier;

use yii\filters\VerbFilter;
use yii\filters\AccessControl;

use modules\main\Module;

//use dominus77\sweetalert2\Alert;
use common\widgets\Alert;

class ChatController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'denyCallback' => function ($rule, $action) {
                    throw new \Exception('У вас нет доступа к этой странице!');

                },
                'only' => ['create','favorites'],
                'rules' => [
                [
                    'actions' => ['create','favorites'],
                    'allow' => true,
                    'roles' => ['@'],
                ],
                [
                    'allow' => true,
                    'roles' => ['moder'],
                ],
                ],
        ],
        'verbs' => [
           'class' => VerbFilter::className(),
           'actions' => [
               'logout' => ['post'],
           ],
       ],
       ];
    }



    public function actionWord($name='ozhigov',$limit=1,$random=true,$search=null){

        $search = (!empty($search))? $search : Yii::$app->request->post('search',null);

        $description='';

        $where = ' WHERE CHAR_LENGTH(word) > 3 ';

        if($name=='words')$where.=" AND code_parent = 0 ";

        if(!empty($search))$where.=" AND word LIKE '%".$search."%' ";

        if($random) $where .=" ORDER BY RAND() ";

        $where .=" limit " . $limit;

        $word = Yii::$app->dbwords->createCommand("SELECT * FROM ".$name." ".$where);//->queryAll();
        //
        //$word = (new \yii\db\Query())->from('word')->limit(25)->filterWhere(['LIKE', 'word','%'.$search.'%']);
        //$SettingRoom->where('id_user=:id_user')->addParams([':id_user' => $UserID]);
        //$sql = $word->sql;

        if($limit>1){

            $word=$word->queryAll();
            //$word=$word->all();

            $str = '';
            foreach ($word as $value=>$key) {
                //$str .= $key['word'].' , ';
                $str .= '<span class="Words">'.$key['word'].' , </span>';
            }

            $word = $str;

        //$word = HtmlPurifier::process($str);

        }else{

            $word=$word->queryOne();
            //$word=$word->one();
            //print_r($word);
            //is_array($word)
            $description = (is_array($word) && $word['description'])?$word['description']:'';
            $word = (is_array($word) && $word['word'])?$word['word']:'';
        }

        //exit($word);
        $request=Yii::$app->request;


        if ($request->isAjax)
        {
            $response = Yii::$app->response;
            $response->format = \yii\web\Response::FORMAT_JSON;
            return  $response->data = ['word'=>$word,'description'=>$description];
        }
        else
        {
            $response = Yii::$app->response;
            $response->format = \yii\web\Response::FORMAT_JSON;
            return  $response->data = ['word'=>$word,'description'=>$description];
            //return $this->redirect(['chat']);
        }

    }

    public function actionIndex($RoomID = 0 , $Address = null , $SortingWord = null, $MyWord = true, $FirstID = null , $LastID = null, $ChatID=0)
    {

        $UserID = Yii::$app->user->id;
        /*
        Yii::$app->db->createCommand('UPDATE chat_setting SET my_room =:my_room , sorting=:sorting  WHERE id_user = :id_user')
           ->bindValue(':my_room ', $MyWord)
           ->bindValue(':sorting ', $SortingWord)
           ->bindValue(':id_user', $UserID)
           ->execute();
        */
        /*
        Yii::$app->db->createCommand()->update('chat_setting', array (
            'my_room' => $MyWord,
            'sorting' => $SortingWord,
            ), 'id_user=:id_user', array (':id_user' => (int) $UserID ))->execute();

        */
        $ChatRoom = (new \yii\db\Query())->from('chat_room')->limit(25);

        if(!empty($UserID)){

            $OrderMyWord ='';
            if($MyWord){
                $OrderMyWord = 'FIELD (id_user, '.$UserID.') DESC ,';
            }

            $SettingRoom ='';
            if($SortingWord=='favorites'){
            $SettingRoom = (new \yii\db\Query())->select('room')->from('chat_setting');
            $SettingRoom->where('id_user=:id_user')->addParams([':id_user' => $UserID]);
            $SettingRoom = $SettingRoom->one();
            }

            switch ($SortingWord):
                case 'populare'  : $ChatRoom->orderBy([new \yii\db\Expression($OrderMyWord.'chats DESC')]); break;
                case 'favorites' : $ChatRoom->orderBy([new \yii\db\Expression($OrderMyWord.'FIELD (id_room, '.$SettingRoom['room'].') DESC')]); break;
                case 'new'       : $ChatRoom->orderBy([new \yii\db\Expression($OrderMyWord.'id_room DESC')]); break;
                default          : $ChatRoom->orderBy([new \yii\db\Expression($OrderMyWord.'id_room DESC')]); break;
            endswitch;


        }else{

            switch ($SortingWord):
                case 'populare' : $ChatRoom->orderBy([new \yii\db\Expression('chats DESC')]); break;
                case 'new'      : $ChatRoom->orderBy([new \yii\db\Expression('id_room DESC')]); break;
                default         : $ChatRoom->orderBy([new \yii\db\Expression('id_room DESC')]); break;
            endswitch;

        }


       //$chatroom = $chatroom->each();
        $ChatRoom = $ChatRoom->all();
       //echo $sql = $chatroom->createCommand()->sql;
       //return false;



        $chats = (new \yii\db\Query())
               ->from('chat')
               ->filterWhere(['id_room' => $RoomID])
               ->andFilterWhere(['id_address' => $Address])
               ->orderBy('id_chat DESC')
               ->limit(20)
               ->offset(2)
               ->indexBy('id_chat') // array
               ->innerJoin('profile', 'profile.id_user = chat.id_sender')
               ->all();



       //$sql= '1';
       //$sql = $chats->createCommand()->sql;
       //
       //$chats = ArrayHelper::toArray($chats, [], false);
       $chats = array_reverse($chats);

       $ChatRoomActive = (new \yii\db\Query())->from('chat_room')->filterWhere(['id_room' => $RoomID])->limit(1)->one();
       //$ChatRoomActive = 1;

       $ChatID = ($ChatID!=0)? $ChatID : Yii::$app->request->post('ChatID',0);

       $edit = (new \yii\db\Query())->from('chat')->filterWhere(['id_chat' => $ChatID])->limit(1)->one();

        //Yii::$app->layout = '_main.php';

       return $this->render('chat', [
           'chatroom'=>($ChatRoom) ? $ChatRoom : '',
           'chats'=>($chats) ? $chats : '',
           'chatroomactive'=>($ChatRoomActive) ? $ChatRoomActive : '',
           'edit'=> ($edit && $edit['text']) ? $edit['text'] : '' ,
           'chat'=>$ChatID
       ]);


    }

    //http://sendword.loc/chat/read/?Room=60&Address=0&FirstID=0&LastID=0&status=true

    public function actionRead($Room=0,$Address=0,$FirstID=0,$LastID=0,$Status='')
    {

        $request = Yii::$app->request;

        $FirstID = ($FirstID!=0)? $FirstID : $request->post('FirstID',0);
        $LastID = ($LastID!=0)? $LastID : $request->post('LastID',0);


        $Room = ($Room!=0)? $Room : $request->post('Room',0);
        $Address = ($Address!=0)? $Address : $request->post('Address',0);

        $Status = ($Status!='') ? $Status : $request->post('Status','');

        $FirstID = intval($FirstID);
        $LastID = intval($LastID);

        $Room = intval($Room);

        $Address = (intval($Address)>0)?intval($Address):null;

        $chat = (new \yii\db\Query)->from('chat');

        if($LastID>0){

            if($Status != 'true')
            {
                $chat->filterWhere(['<','id_chat', $FirstID])->orderBy('id_chat DESC');
            }
            else
            {
                $chat->filterWhere(['>','id_chat', $LastID])->orderBy('id_chat ASC');
            }
        }
        else
        {
            $chat->orderBy('id_chat DESC');  //$order = 'descr';
        }

        $chat
            ->andfilterWhere(['id_room' => $Room])
            ->andFilterWhere(['id_address' => $Address])
            ->limit(20)
            ->indexBy('id_chat')
            ->innerJoin('profile', 'profile.id_user = chat.id_sender');


       $sql = $chat->createCommand();
       $sql = $sql->sql;

      // echo $sql;

        //$chats = $chats->each();
        $chat = $chat->all();


        foreach ($chat as $key=>$val) {
            $chat[$key]['image'] = \frontend\widgets\avatar\AvatarWidget::widget(['id_user'=>$val['id_sender'],'size'=>['width' => '50','height'=>'50']]);
        }


        //$chat = ArrayHelper::index($chat, 'id_chat');

       // print_r($chat);

        $chat = ArrayHelper::toArray($chat, [], false);

        if($LastID==0){
        $chat = array_reverse($chat);
        }

        $chats = array();
        foreach ($chat as $key => $val) {
        $chats['"+'.$val['id_chat'].'"']  = $this->renderPartial('_chat',['chat' => $val]);
        }

        //if ($request->isAjax)
        //{
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

            //\yii\helpers\VarDumper::dump($chats);

            return  Yii::$app->response->data = ['chats' => $chats,'status'=>$Status,'sql'=>$sql];
        //}

    }

    public function actionCreate()
    {
        $chat=$img=$name=$date=null;

        $request = Yii::$app->request;

        $id_sender = Yii::$app->user->id;

        $id_chat = $request->post('ChatID',0);

        $id_room = $request->post('RoomID',0);

        $id_address = $request->post('AddressChat',0);

        $TextChat = $request->post('TextChat','');

        //

        if(!empty($TextChat) && $id_room>0){

            if(!empty($id_chat)&&$id_chat>0){

            $chat = (new \yii\db\Query())->from('chat')->where('id_chat=:id_chat', [':id_chat' => $id_chat])->one();

            if(Yii::$app->user->can('updateOwnPost', ['post' => $chat,])){

                Yii::$app->db->createCommand()->update('chat', ['text' => $TextChat], 'id_chat ='.$id_chat)->execute();

            }

            }else{


            $command = Yii::$app->db->createCommand()->insert('chat', [
                'id_room' => $id_room,
                'id_sender' => $id_sender,
                'id_address' => $id_address,
                'text' => $TextChat,
                'date' => $date
            ])->execute();




            $id_chat = Yii::$app->db->getLastInsertID();
            $date =  Yii::$app->formatter->asDate('now', 'php:Y-m-d').' '.Yii::$app->formatter->asTime('now', 'php:h:m:s');
            $profile = (new \yii\db\Query())->from('profile')->where('id_user=:id')->addParams([':id' => $id_sender])->one();
            $name = $profile['lastname'].' '.$profile['firstname'];
            $img = \frontend\widgets\avatar\AvatarWidget::widget(['id_user'=>$id_sender,'size'=>['width' => '50','height'=>'50']]);



            $chat = (new \yii\db\Query)
                ->from('chat')
                ->filterWhere(['id_chat' => $id_chat])
                ->indexBy('id_chat')
                ->innerJoin('profile', 'profile.id_user = chat.id_sender');

            $sql = $chat->createCommand();
            $sql = $sql->sql;


            $chat = $chat->one();


            $chat['image'] = \frontend\widgets\avatar\AvatarWidget::widget(['id_user'=>$chat['id_sender'],'size'=>['width' => '50','height'=>'50']]);

            $chat = $this->renderPartial('_chat',['chat' => $chat]);
            }
        }



        if ($request->isAjax)
        {
            $response = Yii::$app->response;
            $response->format = \yii\web\Response::FORMAT_JSON;
            return  $response->data = ['chat'=>$chat,'id_chat' =>$id_chat,'text'=>$TextChat, 'date' => $date, 'name'=> $name, 'img' => $img];
        }
        else
        {
            return $this->redirect(['chat']);
        }

    }

    public function actionUpdateRoom($RoomID=0)
    {


        $id_user = Yii::$app->user->id;
        $request = Yii::$app->request;

        $Word = $request->post('TextWord','');
        //$id_user = Yii::$app->user->id;
        $Room = null;

        //$id_user =

        if(!empty($Word)){

            $Room = (new \yii\db\Query())->from('chat_room')->where('id_room=:id_room', [':id_room' => $RoomID])->one();

            if(Yii::$app->user->can('updateOwnPost', ['post' => $Room,])){
            Yii::$app->db->createCommand()->update('chat_room', array (
            'room' => $Word,
            ), 'id_room=:id_room AND id_user=:id_user', array (':id_room' => (int) $RoomID ,':id_user' => (int) $id_user ))->execute();
            }

                    //->getSql()->getRawSql();//->execute(); ;

            //$id_room = Yii::$app->db->getLastInsertID();

            $Room = array('id_room'=>$RoomID,'room'=>$Word,'id_user'=>$id_user,'chats'=>0,'favorit'=>false);

            $Room = $this->renderPartial('_room',['room' => $Room]);

        }

        if ($request->isAjax)
        {
            $response = Yii::$app->response;
            $response->format = \yii\web\Response::FORMAT_JSON;
            return  $response->data = ['word' => $Word, 'room'=>$Room,'id_room'=>$RoomID,'action'=>'update'];
        }
        else
        {
            return $this->render('word',['model'=>'true',]);

        }

        if(!empty($Word)){
            return $this->redirect(['chat/index']);
        }


    }

    public function actionCreateRoom($Word='')
    {

        $id_user = Yii::$app->user->id;
        $request = Yii::$app->request;

        $Word = ($Word)?$Word:$request->post('TextWord');
        //$id_user = Yii::$app->user->id;
        $Room = null;


        if(!empty($Word)){

            Yii::$app->db->createCommand()->insert('chat_room', [
                'room' => $Word,
                'id_user' => Yii::$app->user->id,
            ])->execute();

            $id_room = Yii::$app->db->getLastInsertID();

            $Room = array('id_room'=>$id_room,'room'=>$Word,'id_user'=>$id_user,'chats'=>0,'favorit'=>false);

            $Room = $this->renderPartial('_room',['room' => $Room]);

        }

        if ($request->isAjax)
        {
            $response = Yii::$app->response;
            $response->format = \yii\web\Response::FORMAT_JSON;
            return  $response->data = ['word' => $Word, 'room'=>$Room,'action'=>'create','id_room'=>$id_room];
        }
        else
        {
            return $this->render('word',['model'=>'true',]);

        }

        if(!empty($Word)){
            return $this->redirect(['chat/index']);
        }

    }


    public function actionDeleteRoom($RoomID=0){

        $request = Yii::$app->request;

        $Room = (new \yii\db\Query())->from('chat_room')->where('id_room=:id_room', [':id_room' => $RoomID])->one();

        if(Yii::$app->user->can('updateOwnPost', ['post' => $Room,])){
            Yii::$app->db->createCommand()->delete('chat_room', 'id_room = '.$RoomID)->execute();
            Yii::$app->db->createCommand()->delete('chat', 'id_room = '.$RoomID)->execute();
        }

        if ($request->isAjax)
        {
            $response = Yii::$app->response;
            $response->format = \yii\web\Response::FORMAT_JSON;
            return  $response->data = ['RoomID' => $RoomID,'action'=>'delete'];

        }else{

            return $this->goBack((!empty(Yii::$app->request->referrer) ? Yii::$app->request->referrer : null));

        }
        // return $this->goBack((!empty(Yii::$app->request->referrer) ? Yii::$app->request->referrer : null));

    }

    //sendword.loc/chat/word-next/?LastWord=0&SortWord=favorites&MyWord='true'

    public function actionWordNext($LastWord = 0 , $SortWord = null ,$MyWord = true,$Setting=false)
    {
        $User = Yii::$app->user->id;

        $Request = Yii::$app->request;

        $Setting = ($Setting) ? $Setting : $Request->post('Setting',false);

        if($Setting){

        Yii::$app->db->createCommand()->update('chat_setting', array (
            'my_room' => $MyWord,
            'sorting' => $SortWord,
            ), 'id_user=:id_user', array (':id_user' => (int) $User ))->execute();

        }


        $LastWord = ($LastWord!==0) ? $LastWord : $Request->post('LastWord',0);

        $SortWord = ($SortWord!==0) ? $SortWord : $Request->post('SortWord',0);

        $MyWord = ($MyWord!== 'true') ? $MyWord : $Request->post('MyWord','true');

        $Rooms = (new \yii\db\Query())->from('chat_room');

        if(!empty($User)){

            $OrderMyWord = '';
            if($MyWord == 'true' && !empty($User)){
            $OrderMyWord = 'FIELD (id_user, '.$User.') DESC ,';
            }

            if($SortWord=='favorites'){
            $SettingRoom = (new \yii\db\Query())->select('room_favorites,sorting,my_room ')->from('chat_setting');
            $SettingRoom->where('id_user=:id_user')->addParams([':id_user' => $User]);
            $SettingRoom = $SettingRoom->one();
            $SettingRoom = $SettingRoom['room_favorites'];

            $SettingRoom = preg_replace('(^,|,$)', "", $SettingRoom);

            if(!empty($SettingRoom)){
                $OrderMyWord .='FIELD (id_room, '.$SettingRoom.') DESC';
            }else{
               $OrderMyWord .= ' id_room DESC';
            }
            }

            switch ($SortWord):
            case 'populare'  : $Rooms->orderBy([new \yii\db\Expression($OrderMyWord.'chats DESC')]); break;
            case 'favorites' : $Rooms->orderBy([new \yii\db\Expression($OrderMyWord)]); break;
            case 'new'       : $Rooms->orderBy([new \yii\db\Expression($OrderMyWord.'id_room DESC')]); break;
            default          : $Rooms->orderBy([new \yii\db\Expression($OrderMyWord.'id_room DESC')]); break;
            endswitch;


        }else{

            switch ($SortWord):
                case 'populare' : $Rooms->orderBy([new \yii\db\Expression('chats DESC')]); break;
                case 'new'      : $Rooms->orderBy([new \yii\db\Expression('id_room DESC')]); break;
                default         : $Rooms->orderBy([new \yii\db\Expression('id_room DESC')]); break;
            endswitch;

        }

        $Rooms->offset($LastWord)->limit(25);

        $command = $Rooms->createCommand();
        $sql = $command->sql;

        //$Room = $Room->each();
        $Rooms = $Rooms->all();


        $Rooms = ArrayHelper::toArray($Rooms, [], false);


        if(!empty($SettingRoom)){

            $SettingRooms = explode(',', $SettingRoom);

            if(count($SettingRooms)>1){

                foreach ($Rooms as $key=>$val) {

                    $Rooms[$key]['favorit'] = (in_array($val['id_room'],$SettingRooms))?true:false;
                }
            }
            else
            {
                foreach ($Rooms as $key=>$val) {

                    $Rooms[$key]['favorit'] = ($val['id_room']==$SettingRoom)?true:false;
                }

            }
        }

        //print_r($Rooms);

        if($Rooms){
            //echo 'true';
        }
        else
        {
            //echo 'false';
        }

        $Room = array();
        foreach ($Rooms as $key => $val) {
            $Room['"+'.$val['id_room'].'"'] = $this->renderPartial('_room',['room' => $val]);
        }


        $response = Yii::$app->response;
        $response->format = \yii\web\Response::FORMAT_JSON;

        return $response->data = ['room' => $Room ,'sql'=>$sql];


        //if ($request->isAjax)
        //{}else{}

    }



    public function actioRoomFavorites($RoomID = 0){

        $Request = Yii::$app->request;

        $RoomID = ($RoomID!=0) ? $RoomID : $Request->post('RoomID',0);

        if(!empty($RoomID)){

        $User = Yii::$app->user->id;

        $SettingRoom = (new \yii\db\Query())->select('room_favorites')->from('chat_setting');
        $SettingRoom->where('id_user=:id_user')->addParams([':id_user' => $User]);
        $SettingRoom = $SettingRoom->one();

        $room = $SettingRoom['room'];
        $room = trim($room);

        $SettingRooms = explode(',', $room);
	    $FavoritsCount = count($SettingRooms);

        if($FavoritsCount > 1)
        {
            if(in_array($RoomID,$SettingRooms))
            {
                $RoomFavorits = '';
                for( $i = 0 ; $i < $FavoritsCount; $i++ )
                {
                    if(trim($SettingRooms[$i]) == '' || empty($SettingRooms[$i]) || $SettingRooms[$i] == $RoomID)continue;

                    $separator = ( $i == $FavoritsCount-1 || $SettingRooms[$i+1] == $RoomID) ?  '' : ',';

                    $RoomFavorits .= ($SettingRooms[$i] !== $RoomID) ? $SettingRooms[$i] . $separator : '' ;
                }
                $FavoritsCount = $FavoritsCount - 1;
            }
            else
            {
                $FavoritsCount = $FavoritsCount + 1;

                $RoomFavorits = (!empty($room)) ? $room . ',' . $RoomID : $RoomID ;
            }
        }
        else
	    {
            if($RoomID == intval($room))
            {
                $RoomFavorits = '';
                $FavoritsCount = 0;
            }
            else
            {
                $RoomFavorits = (!empty($room)) ? $room . ',' . $RoomID : $RoomID ;
                $FavoritsCount = 1;
            }
	    }

        Yii::$app->db->createCommand('UPDATE chat_setting SET room_favorites =:room WHERE id_user = :id_user')
           ->bindValue(':room', $RoomFavorits)
           ->bindValue(':id_user', $User)
           ->execute();

        $response = Yii::$app->response;
        $response->format = \yii\web\Response::FORMAT_JSON;

        return $response->data = ['favorits' => $RoomFavorits , 'count'=> $FavoritsCount];
        }
    }





    public function actionDeleteChat($ChatID=0){

    $request = Yii::$app->request;

    $ChatID = ($ChatID!=0) ? $ChatID : $request->post('ChatID',0);

    $chat = (new \yii\db\Query())->from('chat')->where('id_chat=:id_chat', [':id_chat' => $ChatID])->one();

    if (Yii::$app->user->can('updateOwnPost', ['post' => $chat])){
        Yii::$app->db->createCommand()->delete('chat', 'id_chat = '.$ChatID)->execute();

    }

    if ($request->isAjax)
    {
        $response = Yii::$app->response;
        $response->format = \yii\web\Response::FORMAT_JSON;
        return  $response->data = ['data' => 1,];
    }else{

    return $this->goBack((!empty(Yii::$app->request->referrer) ? Yii::$app->request->referrer : null));

    }

    }
    public function actionEdit($RoomID){

    }

}
