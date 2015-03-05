<?php
require_once 'podio-php-4.0.2/PodioAPI.php';

//CONFIG VARIABLES
$client_id = '[id]';
$client_secret = '[ID]';


//global variables
$stories_progressbar_id = [ID]; //progress bar field id
$stories_timeusedbar_id = [ID]; //timeused bar field id

$sprint_progressbar_id = [ID];
$sprint_timeused_id = [ID];

$project_progressbar_id = [ID];
$project_timeused_id = [ID];

$stories_alottedtime_id = [ID]; //duration field
$stories_timelogged_id = [ID]; //duration field
$stories_sprint_id = [ID]; //sprint relationship field in story item
$stories_project_id = [ID]; //project relationship field in story item

$space_id = [ID];
$stories_app_id = [ID];

//ERROR LOG
error_log("validate triggerd");

//SETUP PODIO CLIENT AND REFERENCE SESSION MANAGER TO STORE TOKEN
Podio::setup($client_id, $client_secret,array(
        'session_manager' => 'PodioSession',
    )
);

//TURN ON DEBUGGING
Podio::$debug = true;

//CHECK FOR OR AUTHENTICATE PODIO
if(Podio::is_authenticated()){


}else{
//IF PODIO IS NOT AUTHENTICATED

//try user/pass authenticate. Needed for tasks call
try {
Podio::authenticate_with_password('[EMAIL]','[PASSWORD]');
}catch (PodioError $e) { 
  var_dump($e->body['error_description']);
}//end try authenticate user/pass


}//END IF PODIO AUTHENTICATED

        
//_POST SWITCH CASE
                  
switch ($_POST['type']) {
    case 'hook.verify':
      PodioHook::validate($_POST['hook_id'], array('code' => $_POST['code']));
    case 'item.create':
        $itemid = $_POST['item_id'];
       timeNprogress($itemid);
    case 'item.update':
        $itemid = $_POST['item_id'];
        timeNprogress($itemid);
    case 'task.update':
        $taskid = $_POST['task_id'];
       taskUpdates($taskid);
    case 'item.delete':
        $itemid = $_POST['item_id'];
        
}

//TASK UPDATES (TRIGGER ITEM UPDATE)
function taskUpdates($taskid){

    global $space_id,$stories_progressbar_id,$stories_app_id;
    
    $taskid=$taskid;

    $task = PodioTask::get($taskid);
    $taskRef = $task->ref;
    $parentID = $taskRef->id;
    
    $itemid = $parentID;
    
    //work normally with itemid
    $filteredItem = PodioItem::get($itemid);
    $filteredFields = $filteredItem->fields;
    $appID = $filteredItem->app->app_id;
    
    if($appID == $stories_app_id){ //LIMIT TO TASKS IN STORY ITEMS
        
        //GET CURRENT TASK PARENT PROGRESSBAR AND TASK BAR VALUES
        $currentTaskParentTaskBarValue = 0;
        $currentTaskParent = PodioItem::get($itemid); 
        foreach($currentTaskParent->fields as $currentTaskParentFieldKey => $currentTaskParentFieldValue){
            if($currentTaskParentFieldValue->field_id ==  $stories_progressbar_id){
                $currentTaskParentTaskBarValue = $currentTaskParentFieldValue->values;
            }
        }
        
        //GET TASKS BY PARENT ID (STORY)
        $getTasksAttr = array(  
        'space' => $space_id, //space id of project
        'offset' => 0,
        'reference' => 'item:' . $itemid,
        );
        $allTasks = PodioTask::get_all($getTasksAttr);
        $completedTaskCount = 0;
        $activeTaskCount = 0;
        $allTaskCount = 0;
    
        foreach($allTasks as $task){
            $taskid = $task->task_id;
            $taskRef = $task->ref;
            $refId = $taskRef->id;
            $taskStatus = $task->status;
            if($taskStatus == 'completed'){
                $completedTaskCount ++;
            }elseif($taskStatus == 'active'){
                $activeTaskCount++;
            }
            $allTaskCount++;
                
        };
        
        //PERCENTAGE
        $finalTasksPercentage = round($completedTaskCount*100/$allTaskCount);
    
        //UPDATE IF DIFFERENT
        if($currentTaskParentTaskBarValue != $finalTasksPercentage){
            PodioItem::update($itemid, array('fields' => array($stories_progressbar_id => $finalTasksPercentage)));
        } 
        
    }

}//end task updates

//TIME AND PROGRESS UPDATES
function timeNprogress($itemid){


global $stories_progressbar_id,$stories_timeusedbar_id,$sprint_progressbar_id,$sprint_timeused_id,$project_progressbar_id,$project_timeused_id,$stories_alottedtime_id,$stories_timelogged_id,$stories_sprint_id,$stories_project_id,$space_id,$stories_app_id;
    
//PRIMARY VARIABLES
$itemid=$itemid; //this is the current id coming from $_POST
$filteredItem = PodioItem::get($itemid);
$filteredFields = $filteredItem->fields;
$appID = $filteredItem->app->app_id;

$currentStoryProgressBarValue = 0;
$currentStoryTimeBarValue = 0;
$currentStoryTimeLogged = 0;
$currentStoryTimeAlloted= 0;
    
    
$relSprint = array();
$relProject = array();


//LIMIT TO STORIES BEING EDITED ONLY
if($appID == $stories_app_id){

        
        //loop through fields
    foreach($filteredFields as $field){
        $filteredFieldId = $field->field_id;
        $filteredFieldType = $field->type;
        $filteredFieldValues = $field->values;
        
        //if field is progress bar field (progress)
        if($filteredFieldId == $stories_progressbar_id){ 
        $currentStoryProgressBarValue = intval($filteredFieldValues);
        }
        //if field is progress bar field (progress)
        if($filteredFieldId == $stories_timeusedbar_id){ 
        $currentStoryTimeBarValue = intval($filteredFieldValues);
        }
        //if alotted time field (duration)
        if($filteredFieldId == $stories_alottedtime_id){ 
        $currentStoryTimeAlloted = intval($filteredFieldValues);
        }
        //if time logged in field (duration)
        if($filteredFieldId == $stories_timelogged_id){ 
        $currentStoryTimeLogged = intval($filteredFieldValues);
        }
        if($filteredFieldId==$stories_sprint_id){
            foreach($filteredFieldValues as $value){
                $relSprintId = $value->item_id;
                $relSprint[] = $relSprintId;
            }
        }
        if($filteredFieldId==$stories_project_id){
            foreach($filteredFieldValues as $value){
                $relProjectId = $value->item_id;
                $relProject[] = $relProjectId;
            }
        } 
        
    }
    
    //Time Progress Bar
    
    $finalStoryTimePercentage = round($currentStoryTimeLogged*100/$currentStoryTimeAlloted);
        
    if($currentStoryTimeBarValue != $finalStoryTimePercentage){
        PodioItem::update($itemid, array('fields' => array($stories_timeusedbar_id => $finalStoryTimePercentage)));  //works fine
        //echo '<p>values differ</p>';
    } 
    
    
    //RELATED SPRINTS AND STORIES
    $relSprintUnique = array_unique($relSprint);  
    
    foreach($relSprintUnique as $sprintItem){
        $sprintItemId = $sprintItem;
        
        //GET CURRENT SPRINT PROGRESSBAR AND TASK BAR VALUES
        $currentSprintTimeBarValue = 0;
        $currentSprintTaskBarValue = 0;
        $currentSprint = PodioItem::get($sprintItemId);
        foreach($currentSprint->fields as $currentSprintFieldKey => $currentSprintFieldValue){
            if($currentSprintFieldValue->field_id == $sprint_progressbar_id){
                $currentSprintTaskBarValue = $currentSprintFieldValue->values;
            }
            if($currentSprintFieldValue->field_id == $sprint_timeused_id){
                $currentSprintTimeBarValue = $currentSprintFieldValue->values;
            }
        }  
    
        //GET ALL STORIES POINTING TO CURRENT SPRINT
        
        $sprintRefs = PodioItem::get_references($sprintItemId);
        
        //SPRINT LEVEL VALUES
        $SprintStoryCount = 0; 
        $sprintAllotedTimeArray = array();
        $sprintLoggedTimeArray = array();
        $sprintCompletedTaskCount = 0;
        $sprintActiveTaskCount = 0;
        $sprintAllTaskCount = 0;
        
        //loop through referenced items
        foreach($sprintRefs as $sprintKey => $sprintValue){
            if($sprintValue['app']['app_id'] == $stories_app_id){
                
                $sprintItems = $sprintValue['items'];
                foreach($sprintItems as $sprintItemsKey => $sprintItemsValue){
                    
                    //INDIVIDUAL STORY ITEM LEVEL
                    
                    //ITEM LEVEL VALUES
                    $sprintStoryCount++;
                    $sprintItemIdSub=$sprintItemsValue['item_id'];
                    
                    //ITEM LEVEL - GET TIME VALUES 
                    $sprintItemSub = PodioItem::get($sprintItemIdSub); 
                    
                    
                    
                    foreach($sprintItemSub->fields as $sprintItemFieldSubKey => $sprintItemFieldSubValue){
                        if($sprintItemFieldSubValue->field_id==$stories_timelogged_id){
                            $sprintLoggedTimeArray[] = intval($sprintItemFieldSubValue->values);
                        }
                        if($sprintItemFieldSubValue->field_id==$stories_alottedtime_id){
                            $sprintAllotedTimeArray[] = intval($sprintItemFieldSubValue->values);
                        }
                        
                    }
                    
                
                    //ITEM LEVEL - GET TASK VALUES
                     $sprintTasksAttr = array(  
                    'space' => $space_id, //space id of project board
                    'offset' => 0,
                    'reference' => 'item:' . $sprintItemIdSub,
                    );
                    $sprintTasks = PodioTask::get_all($sprintTasksAttr);
                    
                    foreach($sprintTasks as $sprintTask){
                        
                        //TASK LEVEL VALUES
                        $sprintTaskId = $sprintTask->task_id;
                        $sprintTaskRef = $sprintTask->ref;
                        $sprintRefId = $sprintTaskRef->id;
                        $sprintTaskStatus = $sprintTask->status;
                        
                        if($sprintTaskStatus == 'completed'){
                            $sprintCompletedTaskCount++;
                        }elseif($sprintTaskStatus == 'active'){
                            $sprintActiveTaskCount++;
                        }
                        
                    $sprintAllTaskCount++;
                    };
                    
                     
                }//end for each sprint item
                
            } //if related item is story
            
            
        
        } //loop through each id in the related sprint array
        
            //UPDATE SPRINT INFORMATION
            
            //adding up arrays
            $sprintTasksCount = $sprintAllTaskCount;
            $sprintActiveTasksCount =  $sprintActiveTaskCount;
            $sprintCompletedTasksCount =  $sprintCompletedTaskCount;
            $sprintAllotedTime = array_sum($sprintAllotedTimeArray);
            $sprintLoggedTime = array_sum($sprintLoggedTimeArray);
             
            //percentages
            $finalSprintTimePercentage = round($sprintLoggedTime*100/$sprintAllotedTime);
            $finalSprintTaskPercentage = round($sprintCompletedTasksCount*100/$sprintTasksCount);

        if($currentSprintTimeBarValue != $finalSprintTimePercentage || $currentSprintTaskBarValue != $finalSprintTaskPercentage){
        PodioItem::update($sprintItemId, array('fields' => array($sprint_timeused_id => $finalSprintTimePercentage,$sprint_progressbar_id => $finalSprintTaskPercentage)));
        } 
        
    }
    
    //RELATED PROJECT ITEMS AND STORIES
    $relProjectUnique = array_unique($relProject); 
    
    foreach($relProjectUnique as $projectItem){
        $projectItemId = $projectItem;
        
        //GET CURRENT PROGRESS PROGRESSBAR AND TASK BAR VALUES
        $currentProjectTimeBarValue = 0;
        $currentProjectTaskBarValue = 0;
        $currentProject = PodioItem::get($projectItemId);
        ////var_dump($currentProject);
        foreach($currentProject->fields as $currentProjectFieldKey => $currentProjectFieldValue){
            if($currentProjectFieldValue->field_id == $project_progressbar_id){
                $currentProjectTaskBarValue = $currentProjectFieldValue->values;
            }
            if($currentProjectFieldValue->field_id == $project_timeused_id){
                $currentProjectTimeBarValue = $currentProjectFieldValue->values;
            }
        }

        
        //GET ALL ITEMS POINTINT TO CURRENT PROJECT
        $projectRefs = PodioItem::get_references($projectItemId);
        
        //PROJECT LEVEL VALUES
        $ProjectStoryCount = 0; 
        $projectAllotedTimeArray = array();
        $projectLoggedTimeArray = array();
        $projectCompletedTaskCount = 0;
        $projectActiveTaskCount = 0;
        $projectAllTaskCount = 0;
        
        //loop through referenced items
        foreach($projectRefs as $projectKey => $projectValue){

            if($projectValue['app']['app_id'] == $stories_app_id){
                $projectItems = $projectValue['items'];
                foreach($projectItems as $projectItemsKey => $projectItemsValue){
                    
                    //ITEM LEVEL VALUES
                    $projectStoryCount++;
                    $projectItemIdSub=$projectItemsValue['item_id'];
                    
                    //ITEM LEVEL - GET TIME VALUES 
                    $projectItemSub = PodioItem::get($projectItemIdSub); 
                    
                    foreach($projectItemSub->fields as $projectItemFieldSubKey => $projectItemFieldSubValue){
                        if($projectItemFieldSubValue->field_id==$stories_timelogged_id){
                            $projectLoggedTimeArray[] = intval($projectItemFieldSubValue->values);
                        }
                        if($projectItemFieldSubValue->field_id==$stories_alottedtime_id){
                            $projectAllotedTimeArray[] = intval($projectItemFieldSubValue->values);
                        }
                        
                    }
                    

                    //ITEM LEVEL - GET TASK VALUES
                     $projectTasksAttr = array(  
                    'space' => $space_id, 
                    'offset' => 0,
                    'reference' => 'item:' . $projectItemIdSub,
                    );
                    $projectTasks = PodioTask::get_all($projectTasksAttr);
                    
                    foreach($projectTasks as $projectTask){
                        
                        //TASK LEVEL VALUES
                        $projectTaskId = $projectTask->task_id;
                        $projectTaskRef = $projectTask->ref;
                        $projectRefId = $projectTaskRef->id;
                        $projectTaskStatus = $projectTask->status;
                        
                        if($projectTaskStatus == 'completed'){
                            $projectCompletedTaskCount++;
                        }elseif($projectTaskStatus == 'active'){
                            $projectActiveTaskCount++;
                        }
                        
                    $projectAllTaskCount++;
                    };
                     
                }
                
            } //end if is story reference item

        } //end story level
        
        //PROJECT LEVEL DEBUGGING RESULTS
            
            //adding up arrays
            $projectTasksCount = $projectAllTaskCount;
            $projectActiveTasksCount =  $projectActiveTaskCount;
            $projectCompletedTasksCount =  $projectCompletedTaskCount;
            $projectAllotedTime = array_sum($projectAllotedTimeArray);
            $projectLoggedTime = array_sum($projectLoggedTimeArray);
            
            
            //percentages
            $finalProjectTimePercentage = round($projectLoggedTime*100/$projectAllotedTime);
            $finalProjectTaskPercentage = round($projectCompletedTasksCount*100/$projectTasksCount);
            
        
        if($currentProjectTimeBarValue != $finalProjectTimePercentage || $currentProjectTaskBarValue != $finalProjectTaskPercentage){
        PodioItem::update($projectItemId, array('fields' => array($project_timeused_id => $finalProjectTimePercentage,$project_progressbar_id => $finalProjectTaskPercentage)));
        }
    }
    
    
}//end if stories app id
    
} //end timenprogress function

//PODIO BROWSER SESSION COOKIE. SAVE ACCESS TOKEN TO MINIMIZE LOAD
class PodioBrowserSession {

  /**
   * For sessions to work they must be started. We make sure to start
   * sessions whenever a new object is created.
   */
public function __construct() {
    if(!session_id()) {
      session_start();
    }
  }

  /**
   * Get oauth object from session, if present. We ignore $auth_type since
   * it doesn't work with server-side authentication.
   */
  public function get($auth_type = null) {

    // Check if we have a stored session
    if (!empty($_SESSION['podio-php-session'])) {

      // We have a session, create new PodioOauth object and return it
      return new PodioOAuth(
        $_SESSION['podio-php-session']['access_token'],
        $_SESSION['podio-php-session']['refresh_token'],
        $_SESSION['podio-php-session']['expires_in'],
        $_SESSION['podio-php-session']['ref']
      );
    }

    // Else return an empty object
    return new PodioOAuth();
  }

  /**
   * Store the oauth object in the session. We ignore $auth_type since
   * it doesn't work with server-side authentication.
   */
  public function set($oauth, $auth_type = null) {

    // Save all properties of the oauth object in a session
    $_SESSION['podio-php-session'] = array(
      'access_token' => $oauth->access_token,
      'refresh_token' => $oauth->refresh_token,
      'expires_in' => $oauth->expires_in,
      'ref' => $oauth->ref,
    );

  }
}


?>