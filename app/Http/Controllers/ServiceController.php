<?php namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Service;
use Illuminate\Http\Request;
use App\Service as serviceModel;
use App\Helpers\Helper;
use App\Http\Controllers\ScriptController as Script ;
use App\Http\Controllers\DbController as Db;
use App\Http\Controllers\ViewController as View;
use Session;
use \App\Helpers\DevlessHelper as DLH;

class ServiceController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$services = Service::orderBy('id', 'desc')->paginate(10);

		return view('services.index', compact('services'));
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		return view('services.create');
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function store(Request $request)
	{       //convert word inputs to lowercase
		$service = new Service();

		  
		    $service->name = strtolower($request->input("name"));
                    $service->description = $request->input("description");
                    $service->username = $request->input("username");
                    $service->password = $request->input('password');
                    $service->database = $request->input('database');
                    $service->hostname = $request->input('hostname');
                    $service->driver = $request->input('driver');
                    $service->active = 1;
                    $service->script = 'echo "Happy Coding";';

                    $connection = 
                    [
                        'username' => $service->username,
                        'password' => $service->password,
                        'database' => $service->database,
                        'hostname' => $service->hostname,
                        'driver'   => $service->driver,
                    ];
                    $db = new Db();

                    if(!$db->check_db_connection($connection)){
                        
                         DLH::flash("Sorry connection could not be made to Database", 'error');
                    }
                    else
                    {
                    
                     ($service->save())? DLH::flash("Service created successfully", 'success'):
                        DLH::flash("Service could not be created", 'error');
                    }
                
		return redirect()->route('services.index')->with('message', 'Item created successfully.');
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		$service = Service::findOrFail($id);

		return view('services.show', compact('service'));
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function edit($id)
	{
		$service = Service::findOrFail($id);
                $table_meta = \App\TableMeta::where('service_id',$id)->get();
                $count = 0;
                foreach($table_meta as $each_table_meta)
                {
                    $table_meta[$count]  = (json_decode($each_table_meta->schema, true));
                    $count++;
                }
                
		return view('services.edit', compact('service','table_meta'));
	}

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @param Request $request
	 * @return Response
	 */
	public function update(Request $request, $id)
	{
                
		if($service = Service::findOrFail($id))
                {
                    if($request->input('call_type') =='solo')
                    {
                        $service->script = $request->input('script');
                        $service->save();
                        Helper::interrupt(626);
                    }
                    
                    $service->name = strtolower($request->input("name"));
                    $service->description = $request->input("description");
                    $service->username = $request->input("username");
                    $service->password = $request->input('password');
                    $service->database = $request->input('database');
                    $service->hostname = $request->input('hostname');
                    $service->driver = $request->input('driver');
                    $service->active = $request->input("active");
                    
                    $connection = 
                    [
                        'username' => $service->username,
                        'password' => $service->password,
                        'database' => $service->database,
                        'hostname' => $service->hostname,
                        'driver'   => $service->driver,
                    ];
                    $db = new Db();
                    dd($db->check_db_connection($connection));
                    if(!$db->check_db_connection($connection)){
                        
                         DLH::flash("Sorry connection could not be made to Database", 'error');
                    }
                    else
                    {
                    
                     ($service->save())? DLH::flash("Service updated successfully", 'success'):
                        DLH::flash("Changes did not take effect", 'error');
                    }
                }   
		return back();
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function destroy($id)
	{
		$service = Service::findOrFail($id);
		if($service->delete())
                {
                    DLH::flash("Service deleted successfully", 'success');
                }
                else
                {
                    DLH::flash("Service could not be deleted", 'error');
                }

		return redirect()->route('services.index');
	}
        
        /**
        * all api calls go through here
        * @param array  $request request params 
        * @param string  $service  service to be accessed
        * @param string $resource resource to be accessed
        * @return Response
        */
        public function api(Request $request, $service, $resource)
        {
            $this->resource($request, $service, $resource);
        }
        
        /**
	 * Refer request to the right service and resource  
         * @param array  $request request params 
	 * @param string  $service  service to be accessed
         * @param string $resource resource to be accessed
	 * @return Response
	 */
        public function resource($request, $service, $resource, $internal_access=false)
        {  
            $resource = strtolower($resource);
            $service = strtolower($service);
            ($internal_access == true)? $method = $request['method'] :
            $method = $request->method();
            
            $method = strtoupper($method);
            #check method type and get payload accordingly
         
            if($internal_access == true)
            {
                $parameters = $request['resource'];
                
            }
            else
            {
                $parameters = $this->get_params($method, $request);
                
            }
            
            
            //$resource
            return $this->assign_to_service($service, $resource, $method, 
                    $parameters,$internal_access);
        }
        
       
        
        /**
	 * assign request to a devless service .
	 *
         * @param string $service name of service to be access 
	 * @param  string  $resource
         * @param array $method http verb
         * @param array $parameter contains all parameters passed from route
         * @param boolean $internal_service true if service is being called internally
	 * @return Response
	 */
        public function assign_to_service($service, $resource, $method,
                $parameters=null,$internal_access=false)
        {       
                $current_service = $this->service_exist($service);
                //set temporal login id
                Session::put('user',1);
                //Session::forget('user');
                //check access right 
                $is_it_public = $current_service->public;
                $am_i_logged_in = session()->has('user');
                $accessed_internally = $internal_access;
                
                if($is_it_public == 1 || $am_i_logged_in == true || 
                        $accessed_internally == true)
                {
                    
                
                $payload = 
                    [
                    'id'=>$current_service->id,  
                    'service_name' =>$current_service->name,
                    'database' =>$current_service->database, 
                    'driver' => $current_service->driver,
                    'hostname' => $current_service->hostname,
                    'username' => $current_service->username,    
                    'password' => $current_service->password,   
                    'calls' =>  $current_service->calls,
                    #'public' => $current_service->public,    
                    'script' => $current_service->script,
                    'method' => $method,
                    'params' => $parameters, 
                ]; 
                //keep names of resources in the singular
                 switch ($resource)
                 {
                    case 'db':
                        
                        $db = new Db(); 
                            $db->access_db($resource,$payload);
                            break;    
                            
                    case 'script':
                        
                         $script = new script;
                            $script->run_script($resource,$payload);
                            break;
                            
                    case 'schema':
                        $db = new Db();
                            $db->create_schema($resource, $payload);
                            break;
                    
                    case 'view':
                        return $payload;
                        
                    default:
                        Helper::interrupt(605); 
                 }
                      
                 
                }
                else
                {
                    Helper::interrupt(624);
                }
                    
            }
                 
            /*
             * get parameters from request
             * 
             * @param string $service_name name of service 
             * return array of service values 
             */
            public function service_exist($service_name)
            {
                if($current_service = serviceModel::where('name', $service_name)->
                    where('active',1)->first())
                     {
                             return $current_service;
                     }
                     else
                     {
                         Helper::interrupt(604);
                     }
            }
            
            /*
             * get parameters from request
             * 
             * @param string $method reuquest method type 
             * @param array $request request parameters 
             * return array of parameters
             */
            public function get_params($method, $request)
            {
                if(in_array($method,['POST','DELETE','PATCH']))
                {
                     $parameters = $request['resource'];
                     

                }
                else if($method == 'GET')  
                {
                     $parameters = Helper::query_string();

                }
                else
                {
                    Helper::interrupt(608, 'Request method '.$method.
                            ' is not supported');        
                }
                return $parameters;
            }
        //check for pre and post 
}