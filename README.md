
# Cloud Storage Backend System

This project is a backend system simulating a cloud file storage service where users can upload, delete, and view stored files under a limited storage quota. Built with Laravel and MySQL.

## Features

- User storage quota: 500 MB per user
- Upload, delete, and list files for each user
- Storage summary endpoint
- File deduplication by hash (bonus)
- Concurrency-safe storage limit enforcement

## Project Setup

1. **Clone the repository**
	```bash
	git clone <your-repo-url>
	cd cloud-storage
	```
2. **Install dependencies**
	```bash
	composer install
	```
3. **Copy and edit environment file**
	```bash
	cp .env.example .env
	# Edit .env to set your database credentials
	```
4. **Generate application key**
	```bash
	php artisan key:generate
	```
5. **Run migrations**
	```bash
	php artisan migrate
	```
6. **(Optional) Seed sample users**
	```bash
	php artisan db:seed
	```
7. **Start the development server**
	```bash
	php artisan serve
	```

## Database Setup

Configure your MySQL database in the `.env` file:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cloud_storage
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Run migrations as shown above to create all required tables.

## API Endpoints

All endpoints are prefixed with `/api`.

### Upload File
**POST** `/api/users/{user_id}/files`

Form-data body:
- `file`: (file) The file to upload

### Delete File
**DELETE** `/api/users/{user_id}/files/{file_id}`

### List User Files
**GET** `/api/users/{user_id}/files`

### Storage Summary
**GET** `/api/users/{user_id}/storage-summary`

#### Response Example
```json
{
  "data": {
	 "user_id": 1,
	 "storage_limit_bytes": 524288000,
	 "total_storage_used_bytes": 85354,
	 "remaining_storage_bytes": 524202646,
	 "total_active_files": 1
  }
}
```

## Design Decisions & Assumptions

- **User Quota:** Each user has a 500 MB quota, tracked in the `used_storage_bytes` column.
- **Deduplication:** Physical files are stored once per unique hash. Multiple users can reference the same physical file.
- **Soft Delete:** Files are soft-deleted (marked with `deleted_at`) and do not count toward quota after deletion.
- **No Authentication:** For simplicity, authentication is not implemented.
- **File Content:** Actual file content is stored in `storage/app/user-files/{user_id}/`.

## Concurrency Control

All quota checks and file operations are wrapped in database transactions with row-level locking (`lockForUpdate`) to prevent race conditions and ensure the storage limit is never exceeded, even with simultaneous uploads.

## Scaling Considerations

- **Database:** Use indexed columns for user and file lookups. For 100K+ users, consider sharding or partitioning.
- **Storage:** Use cloud storage (e.g., S3) for file content in production.
- **Queue:** For heavy upload/delete traffic, move file processing to background jobs.

## Sample Requests (cURL)

**Upload File:**
```bash
curl -X POST http://localhost:8000/api/users/1/files \
  -F "file=@/path/to/your/file.pdf"
```

**Delete File:**
```bash
curl -X DELETE http://localhost:8000/api/users/1/files/1
```

**List Files:**
```bash
curl http://localhost:8000/api/users/1/files
```

**Storage Summary:**
```bash
curl http://localhost:8000/api/users/1/storage-summary
```

## License

MIT
