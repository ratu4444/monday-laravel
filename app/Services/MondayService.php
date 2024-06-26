<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class MondayService
{
    public static function guzzleClient()
    {
        return $client = new Client();
    }
    public static function getBaseUrl(): string
    {
        return 'https://api.monday.com/v2/';
    }

    public static function getHeaders(): array
    {
        $monday_credentials = "eyJhbGciOiJIUzI1NiJ9.eyJ0aWQiOjM3MTE0NDU2NywiYWFpIjoxMSwidWlkIjo0MDYzNTU3MiwiaWFkIjoiMjAyNC0wNi0xMlQxMToxNDozOS41ODBaIiwicGVyIjoibWU6d3JpdGUiLCJhY3RpZCI6MTU3OTgwNDcsInJnbiI6InVzZTEifQ.a8R2vlUYswuoDOeKt8_F82g9MI7_po9-m-uoJ99sJMk";

        return [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => $monday_credentials,
        ];
    }
    public static function createBoard($board_name, $board_kind)
    {
        $api = MondayService::getBaseUrl();
        $headers = MondayService::getHeaders();

        $query = [
            'query' => "
                mutation {
                    create_board (board_name: \"$board_name\", board_kind: $board_kind) {
                        id
                        name
                    }
                }
            "
        ];

        $response = self::guzzleClient()->post($api, [
            'headers' => $headers,
            'json' => $query,
        ]);
        return json_decode($response->getBody(), true);
    }


    public static function createGroup($board_id, $group_name, $relative_to, $group_color, $position_relative_method)
    {
        $api = MondayService::getBaseUrl();
        $headers = MondayService::getHeaders();
        $query = [
            'query' => "
                mutation {
                    create_group (
                        board_id:   $board_id,
                        group_name: \"$group_name\",
                        relative_to: \"$relative_to\",
                        group_color: \"$group_color\",
                        position_relative_method: $position_relative_method
                    ) {
                        id
                    }
                }
            "
        ];
        $response = self::guzzleClient()->post($api, [
            'headers' => $headers,
            'json' => $query,
        ]);
        return json_decode($response->getBody(), true);
    }
    public static function createItem($group_id, $board_id,  $item_name, $column_values)
    {
        $api = MondayService::getBaseUrl();
        $headers = MondayService::getHeaders();
        $query = "mutation {
        create_item (board_id: $board_id, group_id: \"$group_id\", item_name: \"$item_name\", column_values: \"$column_values\") {
        id
        }";
        $body = ['query' => $query];
        $response = callExternalPostApi($api, $headers, $body);
        $item_id = $response['Your required item id'];

        return $item_id;
    }
    public static function fetchBoards($limit = 10000)
    {
        $api = self::getBaseUrl();
        $headers = self::getHeaders();
        $query = "query{boards(limit: $limit) {id name}}";
        $body = ['query' => $query];

        $response = callExternalPostApi($api, $headers, $body);
        $boards = $response['Your required Boards'];

        return $boards;

    }

    public static function getSubitemsByItemId($item_id, $column_id)
    {
        $api = self::getBaseUrl();
        $headers = self::getHeaders();
        $query = "query{items(ids:[$item_id]) {id subitems {id column_values(ids:[\"$column_id\"]) {text}}}}";
        $body = ['query' => $query];

        $response = callExternalPostApi($api, $headers, $body);

        return $response['success'] && count($response['data']['data']['items'])
            ? $response['data']['data']['items'][0]['subitems']
            : [];
    }

    public static function getItemsByColumnValues($board_id, $column_id, $column_value)
    {
        $api = self::getBaseUrl();
        $headers = self::getHeaders();
        $query = "query{items_page_by_column_values (limit: 1, board_id:$board_id, columns: {column_id:\"$column_id\", column_values: \"$column_value\"}){cursor items { id group{id}}}}";
        $body = ['query' => $query];

        $response = callExternalPostApi($api, $headers, $body);

        return $response['success']
            ? $response['data']['data']['items_page_by_column_values']['items']
            : [];
    }

    public static function getRepresentativeIdWithName($sales_representative_name)
    {
        $api = self::getBaseUrl();
        $headers = self::getHeaders();
        $query = "query{users(name : \"$sales_representative_name\", limit:1) {id}}";
        $body = ['query' => $query];

        $response = callExternalPostApi($api, $headers, $body);

        return $response['success'] && count($response['data']['data']['users'])
            ? $response['data']['data']['users'][0]['id']
            : null;
    }

    public static function getItemById($item_id): array
    {
        $api = self::getBaseUrl();
        $headers = self::getHeaders();
        $query = "query{items(ids:$item_id){id name column_values{id value}}}";
        $body = ['query' => $query];

        $response = callExternalPostApi($api, $headers, $body);
        if (isset($response['data']['errors'])) {
            $error = $response['data']['errors'][0];
            $response = formatApiResponse(400, $error['message'], $error);
        }

        return $response;
    }

    public static function createUpdate($item_id)
    {
        $api = self::getBaseUrl();
        $headers = self::getHeaders();

        $comment_body = "Invoice";

        $query = "mutation {create_update (item_id: $item_id, body: \"$comment_body\"){id}}";
        $body = ['query' => $query];

        $response = callExternalPostApi($api, $headers, $body);

        return $response['data']['data']['create_update']['id'] ?? null;
    }

    public static function uploadFileToUpdate($file_url, $update_id): array
    {
        $response = Http::get($file_url);
        if (!$response->successful()) return formatApiResponse(400, 'File not found');

        $file_content = $response->body();
        $file_name = 'invoice.pdf';
        $file_path = public_path('invoices/' . $file_name);

        if (!file_exists(dirname($file_path))) {
            mkdir(dirname($file_path), 0755, true);
        }

        file_put_contents($file_path, $file_content);

        $monday_credentials = "Your Monday Credentials";

        $headers = [
            'Authorization' => $monday_credentials,
        ];

        // Multipart Data.
        $data = [
            [
                'name' => 'query',
                'contents' => 'mutation ($file: File!) { add_file_to_update (update_id:'. $update_id .', file: $file) { id } }',
            ],
            [
                'name' => 'variables[file]',
                'contents' => fopen($file_path, 'r'),
                'filename' => $file_name,
            ],
        ];

        $api = MondayService::getBaseUrl() . 'file';

        // Guzzle Client.
        $client = new Client();

        try {
            $response = $client->post($api, [
                'headers' => $headers,
                'multipart' => $data,
            ]);

            $body = json_decode($response->getBody(), true);

            File::delete($file_path);

            return formatApiResponse(200, 'File uploaded successfully', $body['data']);
        } catch (\Exception $exception) {
            File::delete($file_path);
            return formatApiResponse(500, $exception->getMessage());
        }
    }

    public static function formatPhoneNumber($number): string
    {
        if ($number) {
            $number = preg_replace('/\D/', '', $number). ' AU';

//            if (preg_match('/^04\d{8}$/', $number)) {
////                dd(preg_replace('/(\d{4})(\d{3})(\d{3})/', '$1 $2 $3', $number));
//                return preg_replace('/(\d{4})(\d{3})(\d{3})/', '$1 $2 $3', $number);
//            }

//            if (preg_match('/^0[2378]\d{8}$/', $number)) {
////                dd(preg_replace('/^(\d{2})(\d{4})(\d{4})$/', '($1) $2 $3', $number));
//                return preg_replace('/^(\d{2})(\d{4})(\d{4})$/', '($1) $2 $3', $number);
//            }

            return $number;
        } else{
            return '';
        }
    }
}
