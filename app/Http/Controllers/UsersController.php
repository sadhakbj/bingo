<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\CreateUserDTO;
use App\Repositories\IUserRepository;
use App\Services\UserService;
use Bingo\Attributes\Route\ApiController;
use Bingo\Attributes\Route\Body;
use Bingo\Attributes\Route\Get;
use Bingo\Attributes\Route\Headers;
use Bingo\Attributes\Route\Param;
use Bingo\Attributes\Route\Post;
use Bingo\Attributes\Route\Query;
use Bingo\Attributes\Route\Request as ReqAttr;
use Bingo\Attributes\Route\UploadedFile;
use Bingo\Attributes\Route\UploadedFiles;
use Bingo\DTOs\Http\ApiResponse;
use Bingo\Http\Request;
use Bingo\Http\Response;
use Bingo\Http\Sse\StreamedEvent;
use Bingo\Http\StreamedResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile as File;

#[ApiController('/users')]
readonly class UsersController
{
    public function __construct(
        private UserService     $userService,
        private IUserRepository $userRepo,
    ) {}

    #[Get('/')]
    public function index(): Response
    {
        $users = $this->userRepo->all();
        return Response::json([
            'message' => 'List of users',
            'users'   => $users,
        ]);
    }

    #[Get('/search')]
    public function search(
        #[Query('q')] ?string $query = '',
        #[Query('limit')] ?int    $limit = 10,
        #[Query('page')] ?int    $page = 1,
    ): Response {
        return Response::json([
            'message' => 'Search users',
            'query'   => $query,
            'limit'   => $limit,
            'page'    => $page,
            'results' => [],
        ]);
    }

    #[Get('/sse-demo')]
    public function sseDemo(): StreamedResponse
    {
        return Response::eventStream(function (): \Generator {
            foreach ($this->userService->demoUserStreamChunks() as $chunk) {
                yield new StreamedEvent('user', $chunk);
            }
        });
    }

    #[Get('/sse-demo-plain')]
    public function sseDemoPlain(): StreamedResponse
    {
        return Response::eventStream(function (): \Generator {
            foreach ($this->userService->demoUserStreamChunks() as $chunk) {
                yield $chunk;
            }
        });
    }

    // NOTE: Specific routes (/search, /upload, etc.) must be declared before /{id}
    // to prevent the wildcard from swallowing them.

    #[Post('/')]
    public function create(#[Body] CreateUserDTO $dto): Response
    {
        $userDTO  = $this->userService->createUser($dto);
        $response = ApiResponse::success(
            data      : $userDTO,
            message   : 'User created successfully',
            statusCode: 201,
            meta      : ['user_metadata' => $userDTO->getMetadata()],
        );

        return Response::json($response->toArray(), 201);
    }

    #[Get('/{id}')]
    public function show(#[Param('id')] int $id): Response
    {
        $userDTO  = $this->userService->getUserById($id);
        $response = ApiResponse::success(data: $userDTO->toArray());

        return Response::json($response->toArray());
    }

    #[Post('/upload')]
    public function upload(
        #[ReqAttr] Request $request,
        #[Headers('x-api-version')] ?string $apiVersion = null,
        #[UploadedFile('avatar')] ?File   $avatar = null,
    ): Response {
        return Response::json([
            'api_version'    => $apiVersion,
            'avatar_info'    => $avatar
                ? [
                    'name'      => $avatar->getClientOriginalName(),
                    'size'      => $avatar->getSize(),
                    'mime_type' => $avatar->getClientMimeType(),
                    'is_valid'  => $avatar->isValid(),
                ] : null,
            'all_files'      => count($request->files->all()),
            'content_length' => $request->headers->get('Content-Length'),
            'request_id'     => $request->headers->get('X-Request-ID'),
            'timestamp'      => date('c'),
        ]);
    }

    #[Post('/upload-multiple')]
    public function uploadMultiple(
        #[Body] CreateUserDTO $dto,
        #[Headers('x-api-version')] ?string       $apiVersion = null,
        #[UploadedFiles] array         $files = [],
    ): Response {
        $fileInfos = [];
        /** @var File $file */
        foreach ($files as $key => $file) {
            if ($file && $file->isValid()) {
                $fileInfos[$key] = [
                    'name'      => $file->getClientOriginalName(),
                    'size'      => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                    'is_valid'  => $file->isValid(),
                ];
            }
        }

        return Response::json([
            'user_data'      => $dto->toArray(),
            'api_version'    => $apiVersion,
            'uploaded_files' => $fileInfos,
            'total_files'    => count($fileInfos),
        ]);
    }
}
