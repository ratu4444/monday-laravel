<?php

namespace App\Http\Controllers;

use App\Services\MondayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class MondayController extends Controller
{
    public static function createBoard(Request $request)
    {
        $board_name = $request->input('board_name');
        $board_kind = $request->input('board_kind');

        $board_data = MondayService::createBoard($board_name, $board_kind);
        return formatApiResponse('200', 'Board created successfully!', $board_data);
    }
    public function createGroup(Request $request){

        $board_id = $request->input('board_id');
        $group_name = $request->input('group_name');
        $relative_to = $request->input('relative_to');
        $group_color = $request->input('group_color', '#ff642e');
        $position_relative_method = $request->input('position_relative_method', 'before_at');

        $group_data = MondayService::createGroup($board_id, $group_name, $relative_to, $group_color, $position_relative_method);

        return formatApiResponse(200, 'Group created successfully!', $group_data);
    }

    public function createItem($group_id, $board_id){
        $item_name = "Your Item Name";
        $column_values = "your Column Values";

        $item_id = MondayService::createItem($group_id, $board_id, $item_name, $column_values);

        return formatApiResponse(200, 'Item Created Successfully', $item_id);
    }

    public function createUpdate($item_id){
        if (!$item_id){
            return formatApiResponse('500', 'Item ID not found');
        }
        $update_id = MondayService::createUpdate($item_id);
        return formatApiResponse(200, 'Update Created Successfully', $update_id);
    }

    public function uploadFileToUpdate($update_id, $invoice_pdf_download_link): array
    {
        if (!$update_id && $invoice_pdf_download_link){
            return formatApiResponse('500', 'Update ID and Invoice PDF download link not found');
        }
        $response = MondayService::uploadFileToUpdate($invoice_pdf_download_link, $update_id);

        return formatApiResponse(200, 'Upload file to update successfully', $response, );
    }

}
