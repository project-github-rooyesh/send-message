<?php
namespace Esmaili\Message;
use Esmaili\Message\Imports\MessageImport;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Kavenegar\KavenegarApi;
use Esmaili\Message\Models\Message;
use Maatwebsite\Excel\Facades\Excel;

class MessageInterface
{
    protected $user_id = null;
    protected $message = null;
    protected $token = null;
    protected $type = null;
    protected $list_send = null;
    protected $swap_name = null;
    protected $follow_name = null;

    public function __construct($user_id = null,$message = null,$token = null,$list_send = null,$swap_name = null,$follow_name = null)
    {
        $this->user_id = $user_id;
        $this->message = $message;
        $this->token = $token;
        $this->list_send = $list_send;
        $this->swap_name = $swap_name;
        $this->follow_name = $follow_name;
    }


    /**
     * send message in 0098 or kavenegar and store on table
     *
     * @param $array
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function sendMesssage($array){
        $config = Arr::get(config(),'message',false);
        if ($config){
            foreach ($array as $list){
                if (!isset($list['name']) || is_null($list['name'])){
                    $message =  str_replace("%name", $this->swap_name.' '.$this->follow_name.' ', $this->message);
                }else{
                    $message =  str_replace("%name", $list['name'].' '.$this->follow_name.' ', $this->message);
                }
                if ($config['0098'] && $config['0098']['active']){
                    $endpoint = "http://www.0098sms.com/sendsmslink.aspx";
//                    Http::get($endpoint,[
//                        'FROM' => $config['0098']['sender'],
//                        'TO' => $list['mobile'],
//                        'TEXT' => trim($message),
//                        'USERNAME' => $config['0098']['user_name'],
//                        'PASSWORD' => $config['0098']['password'],
//                        'DOMAIN' => '0098',
//                    ]);
                    $client = new \GuzzleHttp\Client();
                    $response = $client->request('GET', $endpoint, [
                        'query' => [
                            'FROM' => $config['0098']['sender'],
                            'TO' => $list['mobile'],
                            'TEXT' => trim($message),
                            'USERNAME' => $config['0098']['user_name'],
                            'PASSWORD' => $config['0098']['password'],
                            'DOMAIN' => '0098',
                        ]]);
                    $this->type = '0098';
                    $this->store($list,$message);
                }
                elseif ($config['kavenegar'] && $config['kavenegar']['active']){
                    $api = new KavenegarApi($config['kavenegar']['api_key']);
                    $results = $api->Send($config['kavenegar']['sender'],$list['mobile'], $message);
                    $this->type = 'kavenegar';
                    $this->store($list,$message);
                }
            }

        }
        return true;
    }



    /**
     * send message with data
     *
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createMessage(){
        $this->sendMesssage($this->list_send);
        return true;
    }



    /**
     * store data in database
     *
     * @param $list
     * @param $message
     * @return void
     */
    protected function store($list, $message){
        Message::create([
            'customer_id' => $list['customer_id'] ?? null,
            'name'        => $list['name'] ?? null,
            'user_id'     => $this->user_id,
            'mobile'      => $list['mobile'],
            'message'     => $message,
            'token'       => $this->token,
            'type'       => $this->type,
        ]);
    }



    /**
     * add page and limit for show data
     *
     * @param $table
     * @param $list
     * @return mixed
     */
    protected function addLimit($table, $list){
        if (!isset($list['page'])){
            $list['page'] = 0;
        }
        if (!isset($list['limit'])){
            $list['limit'] = 10;
        }
        if ($list['limit']>50){
            $list['limit'] = 50;
        }
        $list['count'] = $table->count();
        $list['rows'] = $table->offset( $list['page']*$list['limit'])
            ->limit($list['limit'])
            ->get();
        $list['page'] = (int)$list['page'];
        $list['limit'] = (int)$list['limit'];
        return $list;
    }



    /**
     * show all data with search and filter
     *
     * @param $data
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($data){
        $message = Message::orderBy('id','desc');
        if (isset($data['filters'])){
            $this->filter($message,$data['filters']);
        }
        if (isset($data['search'])){
            $this->search($message,$data['search']);
        }
        return response()->json($this->addLimit($message,$data));

    }



    /**
     * filter data
     *
     * @param $message
     * @param $filters
     * @return void
     */
    protected function filter(&$message, $filters){
        foreach ($filters as $filter){
            if ($filter['field'] == 'name'){
                $message->where('name','like', '%' . $filter['value'] . '%');
            }
            if($filter['field'] == 'message'){
                $message->where('message','like','%' . $filter['value'] . '%');
            }
            if($filter['field'] == 'type'){
                $message->where('type','like','%'.$filter['value'].'%');
            }
            if($filter['field'] == 'mobile'){
                $message->where('mobile','like','%'.$filter['value'].'%');
            }
            if($filter['field'] == 'customer_id'){
                $message->where('customer_id',$filter['value']);
            }
            if($filter['field'] == 'user_id'){
                $message->where('user_id',$filter['value']);
            }
        }
    }



    /**
     * search in data
     *
     * @param $message
     * @param $search
     * @return void
     */
    protected function search(&$message, $search){
        $message->where('name', 'like' , '%' . $search . '%')
            ->Orwhere('message' ,'like', '%' . $search . '%')
            ->Orwhere('mobile','like','%'.$search.'%');
    }



    /**
     * get excel file and send message and store data
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function insertExcel(Request $request){
        $array = Excel::toArray(new MessageImport(), $request->file('file')->store('temp'));
        $this->sendMesssage($array[0]);
        return response()->json(true);
    }



}
