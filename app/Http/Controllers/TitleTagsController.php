<?php

namespace App\Http\Controllers;

use App\Title;
use Common\Core\BaseController;
use Common\Tags\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TitleTagsController extends BaseController
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Tag
     */
    private $tag;

    /**
     * @var Title
     */
    private $title;

    /**
     * @param Request $request
     * @param Tag $tag
     * @param Title $title
     */
    public function __construct(Request $request, Tag $tag, Title $title)
    {
        $this->request = $request;
        $this->tag = $tag;
        $this->title = $title;
    }

    public function store($titleId)
    {
        $this->authorize('update', Title::class);

        $this->validate($this->request, [
            'name' => 'required|string|min:1|max:100',
            'display_name' => 'string|min:1|max:100',
            'type' => 'required|string|min:3|max:30'
        ]);

        $tag = $this->request->all();
        $relation = $this->getRelationName(($this->request->get('type')));
        $tags = $this->tag->insertOrRetrieve(collect([$tag]));

        $this->title
            ->findOrFail($titleId)
            ->$relation()
            ->syncWithoutDetaching($tags->pluck('id'));

        return $this->success(['tag' => $tags->first()]);
    }

    public function destroy($titleId, $type, $tagId)
    {
        $this->authorize('update', Title::class);

        $relation = $this->getRelationName($type);

        $this->title
            ->findOrFail($titleId)
            ->$relation()
            ->detach([$tagId]);

        return $this->success();
    }

    private function getRelationName($type) {
        if ($type === 'production_country') {
            $type = 'country';
        }
        return Str::plural($type);;
    }
}
