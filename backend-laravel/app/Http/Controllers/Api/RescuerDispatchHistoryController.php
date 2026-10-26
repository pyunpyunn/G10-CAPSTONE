<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Presenters\RescuerAssignmentPresenter;
use App\Services\Mobile\RescuerMobileSupport;
use App\Support\RescuerHouseholdQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RescuerDispatchHistoryController extends Controller
{
    public function index(Request $request, RescuerMobileSupport $support, RescuerAssignmentPresenter $presenter)
    {
        $responder = $support->responderForUser($request->user());
        abort_unless($responder,404,'Rescuer account not found.');
        $rows = DB::table('responder_assignments')->where(fn ($q) => $q->where('responder_id',$responder->responder_id)
            ->orWhereJsonContains('route_notes->selected_responder_ids',(int)$responder->responder_id))
            ->orderByDesc('assigned_at')->orderByDesc('assignment_id')->paginate(20);
        return response()->json(['data'=>['assignments'=>$rows->getCollection()->map(function ($row) use ($responder,$presenter) {
            $status = RescuerHouseholdQueue::status($row,(int)$responder->responder_id);
            return ['assignment_id'=>$row->assignment_id,'assignment_code'=>$row->assignment_code,'assigned_area'=>$row->assigned_area,
                'assigned_at'=>$row->assigned_at,'status_key'=>$status,'status_label'=>$presenter->statusLabel($status)];
        })->values(), 'pagination'=>['current_page'=>$rows->currentPage(),'last_page'=>$rows->lastPage(),'total'=>$rows->total()]]]);
    }
}
