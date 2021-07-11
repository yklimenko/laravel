<?php namespace Common\Files\Controllers;

use Common\Core\BaseController;
use Common\Files\Actions\CreateFileEntry;
use Common\Files\Actions\Storage\StorePublicUpload;
use Common\Files\FileEntry;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicUploadsController extends BaseController {

    /**
     * @var Request
     */
    private $request;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Store video or music files without attaching them to any database records.
     *
     * @return JsonResponse
     */
    public function videos()
    {
        $this->authorize('store', FileEntry::class);

        $this->validate($this->request, [
            'type'    => 'required_without:path|string|min:1',
            'path'    => 'required_without:type|string|min:1',
            'file' => 'required|file'
        ]);

        $fileEntry = $this->storePublicFile();

        return response(['fileEntry' => $fileEntry], 201);
    }

    /**
     * Store images on public disk.
     *
     * @return ResponseFactory|Response
     */
    public function images() {

        $this->authorize('store', FileEntry::class);

        $this->validate($this->request, [
            'type'    => 'required_without:path|string|min:1',
            'path'    => 'required_without:type|string|min:1',
            'file' => 'required|file'
        ]);

        $fileEntry = $this->storePublicFile();

        return response(['fileEntry' => $fileEntry], 201);
    }

    /**
     * @return FileEntry
     */
    private function storePublicFile()
    {
        $type = $this->request->get('type');
        $uploadFile = $this->request->file('file');
        $publicPath = $this->request->has('path') ? $this->request->get('path') : "{$type}_media";

        $fileEntry = app(CreateFileEntry::class)->execute($uploadFile, ['public_path' => $publicPath]);

        app(StorePublicUpload::class)->execute($fileEntry, $uploadFile);

        return $fileEntry;
    }
}
