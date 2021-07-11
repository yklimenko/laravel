<?php

namespace App\Http\Controllers;

use App\Listable;
use App\Services\Lists\AttachListItem;
use Carbon\Carbon;
use DB;
use App\Title;
use App\Person;
use App\Episode;
use App\ListModel;
use Common\Core\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListItemController extends BaseController
{
    /**
     * @var ListModel
     */
    private $list;

    /**
     * @var Request
     */
    private $request;

    /**
     * ListItemController constructor.
     * @param ListModel $list
     * @param Request $request
     */
    public function __construct(ListModel $list, Request $request)
    {
        $this->list = $list;
        $this->request = $request;
    }

    /**
     * @param int $listId
     * @return JsonResponse
     */
    public function add($listId)
    {
        $list = $this->list->findOrFail($listId);

        $this->authorize('update', $list);

        $this->validate($this->request, [
            'itemId' => 'required|integer',
            'itemType' => 'required|string'
        ]);

        app(AttachListItem::class)->execute($list, $this->request->all());

        return $this->success(['list' => $list]);
    }

    /**
     * @param int $listId
     * @return JsonResponse
     */
    public function remove($listId)
    {
        $list = $this->list->findOrFail($listId);

        $this->authorize('update', $list);

        $this->validate($this->request, [
            'itemId' => 'required|integer',
            'itemType' => 'required|string'
        ]);

        app(Listable::class)->where([
            'list_id' => $list->id,
            'listable_type' => $list->getListableType($this->request->get('itemType')),
            'listable_id' => $this->request->get('itemId')
        ])->delete();

        return $this->success(['list' => $list]);
    }
}
