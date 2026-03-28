<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\CreateUserDTO;
use App\Http\Middleware\LogMiddleware;
use App\Models\User;
use App\Services\UserService;
use Core\Attributes\ApiController;
use Core\Attributes\Get;
use Core\Attributes\Middleware;
use Core\Attributes\Post;
use Core\Attributes\Route\Body;
use Core\Attributes\Route\Headers;
use Core\Attributes\Route\Param;
use Core\Attributes\Route\Query;
use Core\Attributes\Route\Request as ReqAttr;
use Core\Attributes\Route\UploadedFile;
use Core\Attributes\Route\UploadedFiles;
use Core\Http\Request;
use Core\Http\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile as File;

#[ApiController('/users')]
#[Middleware([LogMiddleware::class])]
class UsersController
{
    public function __construct(private readonly UserService $userService)
    {
    }

    #[Get('/')]
    public function index(): Response
    {
        return Response::json([
            'message' => 'List of users',
            'users'   => User::all()->toArray()
        ]);
    }

    #[Get('/search')]
    public function search(
        #[Query('q')] ?string $query = '',
        #[Query('limit')] ?int $limit = 10,
        #[Query('page')] ?int $page = 1
    ): Response {
        return Response::json([
            'message' => 'Search users',
            'query'   => $query,
            'limit'   => $limit,
            'page'    => $page,
            'results' => []
        ]);
    }

    // NOTE: Specific routes (/search, /upload, etc.) must be declared before /{id}
    // to prevent the wildcard from swallowing them.

    #[Post('/')]
    public function create(#[Body] CreateUserDTO $dto): Response
    {
        $userService = new UserService();
        $response    = $userService->createUser($dto);

        return Response::json($response->toArray(), $response->status_code);
    }

    #[Get('/{id}')]
    public function show(#[Param('id')] int $id): Response
    {
        $response    = $this->userService->getUserById($id);

        return Response::json($response->toArray(), $response->status_code);
    }

    #[Post('/upload')]
    public function upload(
        #[ReqAttr] Request $request,
        #[Headers('x-api-version')] ?string $apiVersion = null,
        #[UploadedFile('avatar')] ?File $avatar = null
    ): Response {
        return Response::json([
            'api_version' => $apiVersion,
            'avatar_info' => $avatar ? [
                'name'      => $avatar->getClientOriginalName(),
                'size'      => $avatar->getSize(),
                'mime_type' => $avatar->getClientMimeType(),
                'is_valid'  => $avatar->isValid()
            ] : null,
            'all_files'      => count($request->files->all()),
            'content_length' => $request->headers->get('Content-Length'),
            'request_id'     => $request->headers->get('X-Request-ID'),
            'timestamp'      => date('c')
        ]);
    }

    #[Post('/upload-multiple')]
    public function uploadMultiple(
        #[Body] CreateUserDTO $dto,
        #[Headers('x-api-version')] ?string $apiVersion = null,
        #[UploadedFiles] array $files = []
    ): Response {
        $fileInfos = [];
        /** @var File $file */
        foreach ($files as $key => $file) {
            if ($file && $file->isValid()) {
                $fileInfos[$key] = [
                    'name'      => $file->getClientOriginalName(),
                    'size'      => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                    'is_valid'  => $file->isValid()
                ];
            }
        }

        return Response::json([
            'user_data'      => $dto->toArray(),
            'api_version'    => $apiVersion,
            'uploaded_files' => $fileInfos,
            'total_files'    => count($fileInfos)
        ]);
    }
}
