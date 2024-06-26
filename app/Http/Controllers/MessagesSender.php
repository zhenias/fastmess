<?php
  
  namespace App\Http\Controllers;
  use Illuminate\Support\Facades\DB;
  use Illuminate\Http\Request;
  use App\Http\Controllers\checkedAccessToken;
  use Illuminate\Contracts\Encryption\Encrypter;
  
  use App\Http\Controllers\ParserDomUrl;
  
  class MessagesSender extends Controller
  {
    protected $chat_id;
    public function chechedChatId($user_id_chats = '', $user_id = '') {
      $chat_check = DB::table('chats')->where(function ($query) use ($user_id, $user_id_chats) {
        $query->where('user_id', '=', $user_id)
          ->where('user_id_chats', '=', $user_id_chats)
          ->orWhere('user_id_chats', '=', $user_id)
          ->where('user_id', '=', $user_id_chats);
      })->first();
      if (!$chat_check) {
        return callback_return(false, 404, 'Chat not found');
      }else {
        return callback_return(true, 200, $chat_check);
      }
    }
    public function getIdMessageInChat() {
      
    }
    static public function getLastMessage($encrypter, $chat_id) {
      // , Encrypter $encrypter
      # $encrypter = new Encrypter();
      $result = DB::table('messages_chats')
        ->select('dt_send', 'content', 'user_id')
        ->where('chat_id', $chat_id)
        ->orderBy('id_messages', 'desc')
        ->limit(1)
        ->first();
      if ($result) {
        return array(
          "text" => $encrypter->decrypt($result->content),
          "dt" => $result->dt_send,
          "user_id" => $result->user_id
        );
      }
      
    }
    public function getChatIn($encrypter, $chat_id = '', $checkedUser) {
      $chats = DB::table('messages_chats')->select()->where('chat_id', $chat_id)->orderBy('id_messages', 'asc')->get();
      $my = array();
      // $my['user'] = $checkedUser->checkUserInChat($chat_id)->getData()->description;
      foreach($chats as $chat) {
        $my[] = array(
          "text" => $encrypter->decrypt($chat->content),
          "preview_link_data" => json_decode($chat->preview_link_data, true),
          "user" => $checkedUser->checkUserInChat($chat->user_id)->getData()->description,
          "user_id" => $chat->user_id,
          "dt_send" => $chat->dt_send,
          "is_edit" => $chat->is_edit,
          "id_message" => $chat->id_messages,
          "parse_mode" => $chat->parse_mode
        );
      }
      return $my;
    }
    public function checkedLinkForChannelOrGroup($link = '') {
      $check = DB::table('channels_link')->where('link', $link)->first();
      if (!$check) {
        return callback_return(false, 401, 'Invalid link');
      }else if ($check->max_user_intive <= $check->short_users && ($check->max_user_intive != 0)) {
        return callback_return(false, 401, 'Expirens link');
      }else {
        return callback_return(true, 200, true);
      }
    }
    public function addedRecordChat($user_id, $chat_id, $intiveLink = '', $type = '', $is_admin = false) {
      if ($type == 'channel') {
        $channels = $is_admin;
        $group = 0;
      }else if ($type == 'group') {
        $channels = 0;
        $group = $is_admin;
      }else {
        $channels = 0;
        $group = 0;
      }
      $insertData = array(
        "chat_id" => NULL,
        "user_id" => $user_id,
        "user_id_chats" => $chat_id,
        "admin_user_id" => $user_id,
        "dt_create" => time(),
        "link_channels" => $intiveLink,
        "is_admin_group" => $group,
        "is_admin_channels" => $channels,
      );
      $datas_added = DB::table('chats')->insert($insertData);
      if ($datas_added) {
        $return = 'Chat create';
      }else {
        $return = 'Chat not create';
      }
      
      return $return;
    }
    
    public function createChat(Request $request, checkedAccessToken $checkedAccessToken, checkedUser $checkedUser) {
      $check_token = $checkedAccessToken->index($request['access_token'])->getData();
      $user_id = $check_token->description->user_id;
      $chat_id = $request['chat_id'];
      $intive_link = $request['intive_link'];
      if (!$check_token->ok) {
        return callback_return($check_token->ok, $check_token->error_code, $check_token->description);
      }else if (!$chat_id) {
        return callback_return(false, 400, 'Missing required parametr chat_id');
      }else {
        $check = $this->chechedChatId($chat_id, $user_id)->getData();
        if ($check->ok) {
          return callback_return(false, 200, 'Chat exits');
        }else {
          // checkedLinkForChannelOrGroup
          $check_type = $checkedUser->checkUserInChat($chat_id)->getData();
          $getInfoMainUserSender = $checkedUser->checkUserInChat($user_id)->getData();
          //echo callback_return(false, 0, $check_type);
          if (!$check_type->ok) {
            return callback_return(false, 404, 'User not found');
          }else {
            if ($check_type->description->type == 'user') {
              return callback_return(true, 200, $this->addedRecordChat($user_id, $chat_id));
            }else if ($check_type->description->type == 'bot') {
              if ($check_token->description->user_id == $chat_id) {
                return callback_return(false, 400, 'The bot cannot write to itself');
              }else if ($getInfoMainUserSender->description->is_support == 1 || $getInfoMainUserSender->description->is_father == 1) {
                return callback_return(true, 200, $this->addedRecordChat($user_id, $chat_id));
              }else {
                return callback_return(false, 400, 'The bot cannot start a conversation with the user');
              }
            }else if ($check_type->description->type == 'group') {
              return callback_return(true, 200, $this->addedRecordChat($user_id, $chat_id, $intive_link));
            }else if ($check_type->description->type == 'channel') {
              /*
              $checkedLinkForChannelOrGroup = $this->checkedLinkForChannelOrGroup($intive_link)->getData();
              if ($check->description->is_private) { }
              */
              return callback_return(true, 200, $this->addedRecordChat($user_id, $chat_id, $intive_link));
            }else {
              return callback_return(false, 404, 'User not found');
            }
            
          }
        }
      }
    }
    public function getChat(Request $request, checkedUser $checkedUser, checkedAccessToken $checkedAccessToken, Encrypter $encrypter) {
      $check_token = $checkedAccessToken->index($request['access_token'])->getData();
      $chat_id = $request['chat_id'];
      
      $user_id = $check_token->description->user_id;
      $uid = $chat_id == $user_id ? $user_id : $chat_id;
      $u = $checkedUser->checkUserInChat($uid)->getData();
      $chat = $this->chechedChatId($uid, $user_id)->getData();
      
      //$uinfo = $checkedUser->checkUserInChat($uid)->getData();
      
      if (isset($chat->description->chat_id)) {
        $cid = $chat->description->chat_id;
      }else $cid = null;
      
      if (!$check_token->ok) {
        return callback_return($check_token->ok, $check_token->error_code, $check_token->description);
      }else if (!$chat_id) {
        return callback_return(false, 400, 'Missing required parametr chat_id');
      }else if (!$u->ok) {
        return callback_return(false, 400, 'Chat not found');
      }else {
        return callback_return(true, 200, array(
          "user" => $u->description,
          "chat" => $this->getChatIn($encrypter, $cid, $checkedUser)
        ));
      }
    }
    public function getChats(Request $request, checkedUser $checkedUser, checkedAccessToken $checkedAccessToken, Encrypter $encrypter) {
      $check_token = $checkedAccessToken->index($request['access_token'])->getData();
      if (!$check_token->ok) {
        return callback_return($check_token->ok, $check_token->error_code, $check_token->description);
      }else {
        $user_id = $check_token->description->user_id;
        
        $getChats = DB::table('chats')->where(function ($query) use ($user_id) {
          $query->where('user_id', '=', $user_id)
            ->orWhere('user_id_chats', '=', $user_id)
            ->orWhere('user_id_chats', '=', $user_id)
            ->orWhere('user_id', '=', $user_id);
        })->get();
        foreach ($getChats as $chat) {
          $uid = $chat->user_id == $user_id ? $chat->user_id_chats : $chat->user_id;
          $u = $checkedUser->checkUserInChat($uid)->getData();
          
          $my[] = array(
            "chat_id" => $uid,
            "user" => $u->description,
            "last_message" => self::getLastMessage($encrypter, $chat->chat_id)
          );
        }
        return callback_return(true, 200, $my);
      }   
    }
    public function id_message($chat_id) {
      $count = DB::table('messages_chats')->where('chat_id', $chat_id)->count();
      return $count;
    }

    public function editTextMessage($access_token, Request $request, Encrypter $encrypter, checkedUser $checkedUser, checkedAccessToken $checkedAccessToken) {
      $chat_id = $request->chat_id;
      $message_id = $request->message_id;
      $check_token = $checkedAccessToken->index($access_token)->getData();
      $text = $request->text;
      
      if (!$chat_id) {
        return callback_return(false, 400, 'Missing required parametr chat_id');
      }else if (!$check_token->ok) {
        return callback_return($check_token->ok, $check_token->error_code, $check_token->description);
      }else {
        $get_chat_id = $this->chechedChatId($chat_id, $check_token->description->user_id)->getData();
        $chat = DB::table('messages_chats')->select()->where('chat_id', $get_chat_id->description->chat_id)->where('id_messages', $message_id)->orderBy('id_messages', 'asc')->first();
        
        if (!$chat) {
          return callback_return(false, 404, 'Message not found');
        }else if (!$message_id) {
          return callback_return(false, 404, 'Missing required parametr message_id');
        }else if ($check_token->description->user_id !== $chat->user_id) {
          return callback_return(false, 403, 'You do not have permission to edit this message');
        }else {
          if (!$text) {
            return callback_return(false, 400, 'Missing required parametr text');
          }else {
            return callback_return(true, 200, array(
              "status" => "Poczekaj..."
            ));
          }
          
        }
      }
    }

    public function deleteMessage($access_token, Request $request, Encrypter $encrypter, checkedUser $checkedUser, checkedAccessToken $checkedAccessToken) {
      $chat_id = $request->chat_id;
      $message_id = $request->message_id;
      $check_token = $checkedAccessToken->index($access_token)->getData();
      
      if (!$chat_id) {
        return callback_return(false, 400, 'Missing required parametr chat_id');
      }else if (!$check_token->ok) {
        return callback_return($check_token->ok, $check_token->error_code, $check_token->description);
      }else {
        $get_chat_id = $this->chechedChatId($chat_id, $check_token->description->user_id)->getData();
        $chat = DB::table('messages_chats')->select()->where('chat_id', $get_chat_id->description->chat_id)->where('id_messages', $message_id)->orderBy('id_messages', 'asc')->first();
        
        if (!$chat) {
          return callback_return(false, 404, 'Message not found');
        }else if (!$message_id) {
          return callback_return(false, 404, 'Missing required parametr message_id');
        }else if ($check_token->description->user_id !== $chat->user_id) {
          return callback_return(false, 403, 'You do not have permission to edit this message');
        }else {
          $del = DB::delete("DELETE FROM messages_chats WHERE chat_id = ? AND id_messages = ?", array($get_chat_id->description->chat_id, $message_id));
          if (!$del) {
            return callback_return(false, 500, 'Can\'t delete message');
          }else return callback_return(true, 200, 'Message deleted');
        }
      }
    }
    
    public function sendMessage($access_token, Request $request, Encrypter $Encrypter) {//, Request $request, checkedAccessToken $checkedAccessToken, Encrypter $Encrypter, ParserDomUrl $ParseDomUrl) {
      //$request = new Request();
      $checkedAccessToken = new checkedAccessToken();
      // $Encrypter = \Illuminate\Contracts\Encryption\Encrypter();
      $ParseDomUrl = new ParserDomUrl();
      $id_mess = array();
      
      $check = $checkedAccessToken->index($access_token)->getData();
      $check_chat = $this->chechedChatId($request['chat_id'], $check->description->user_id)->getData();
      $parse_mode = $request['parse_mode'];
      $text = $request['text'];
      
      if (!$parse_mode || $parse_mode == 'Markdown' || $parse_mode == 'markdown') {
        $parse_mode = 'Markdown';
        //$text = $text ? markdown($text) : $text;
      }else if ($parse_mode == 'html' || $parse_mode == 'HTML') {
        $parse_mode = 'HTML';
      }else {
        $parse_mode = 'Markdown';
        //$text = $text ? markdown($text) : $text;
      }

      $user_id = $check->description->user_id;
      $chat_id = $request['chat_id'];
      $checkedUser = new checkedUser();
      $from_user = $checkedUser->checkUserInChat($user_id)->getData()->description;

      $disable_web_page_preview = boolval($request['disable_web_page_preview']);
      
      $urlRegex = '/(?<!href=")(?:(?:(?:https?:\/\/)?(?:www\.)?)?(?:(?:t\.me\/s\/\w+|t\.me\/\w+)|(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(?:\/\S*)?))/i';
      
      # $preview_link_data = $request['preview_link_data'];
      
      
      $com_chat_id = $chat_id != $user_id ? $chat_id : $user_id;
      if (!$check->ok) {
        return callback_return($check->ok, $check->error_code, $check->description);
      }else if (!$request['chat_id']) {
        return callback_return(false, 400, 'Missing required parametr chat_id');
      }else if (!$check_chat->ok) {
        return callback_return($check_chat->ok, $check_chat->error_code, $check_chat->description);
      }else if (!$request['text']) {
        return callback_return(false, 400, 'Missing required parametr text');
      }else {
        $id_mess = $this->id_message($check_chat->description->chat_id) + 1;
        $text_crypt = $Encrypter->encrypt($text);
        if ($disable_web_page_preview == true) {
          $preview_link_data = null; 
        }else { 
          if (preg_match($urlRegex, $text, $matches)) {
            $preview_link_data = json_encode($ParseDomUrl->parseLinkPreview($matches[0])->getData()->description);
          }else { $preview_link_data = null; }
          
        }
        
        $insertData = array(
          "id_mess" => NULL,
          "id_messages" => $id_mess,
          "chat_id" => $check_chat->description->chat_id,
          "user_id" => $user_id,
          "content" => $text_crypt,
          "user_id_readed" => 0,
          "is_edit" => 0,
          "dt_add" => time(),
          "dt_send" => time(),
          "preview_link_data" => ($preview_link_data),
          "parse_mode" => $parse_mode
        );
        $insert_db = DB::table('messages_chats')->insert($insertData);
        return callback_return(true, 200, array(
          "from" => $from_user,
          "text" => $text,
          "dt_add" => $insertData['dt_add'],
          "id_message" => $id_mess,
          "user_id" => $user_id,
          "parse_mode" => $parse_mode,
          "link_preview" => json_decode($preview_link_data, true)
        ));
      }
    }
    
    public function sendM() {}
  }
