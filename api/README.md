# FoodSave API Documentation

## Base URL
```
/FoodSave/api
```

## Authentication
All endpoints except `/auth/login` and `/auth/register` require JWT authentication. Include the JWT token in the Authorization header:
```
Authorization: Bearer <token>
```

## Endpoints

### Authentication

#### Login
- **URL**: `/auth/login`
- **Method**: `POST`
- **Body**:
  ```json
  {
    "email": "string",
    "password": "string"
  }
  ```
- **Response**:
  ```json
  {
    "status": "success",
    "token": "string",
    "user": {
      "id": 1,
      "full_name": "Springfield Food Bank",
      "email": "foodbank@example.com",
      "user_type": "ngo",
      "organization_name": "Springfield Food Bank"
    }
  }
  ```

#### Register
- **URL**: `/auth/register`
- **Method**: `POST`
- **Body**:
  ```json
  {
    "full_name": "string",
    "email": "string",
    "password": "string",
    "user_type": "string",     // "donor" or "ngo"
    "phone": "string",
    "organization_name": "string"  // required for NGOs
  }
  ```
- **Response**:
  ```json
  {
    "status": "success",
    "message": "Registration successful",
    "user_id": 42,
    "token": "string" // only present when the account is auto-activated (e.g. donor accounts)
  }
  ```

#### Refresh Token
- **URL**: `/auth/refresh`
- **Method**: `POST`
- **Headers**: Requires valid JWT token
- **Response**:
  ```json
  {
    "token": "string"
  }
  ```

### Donations

#### List Donations
- **URL**: `/donations`
- **Method**: `GET`
- **Query Parameters**:
  - `category` (optional): Filter by food category
  - `city` (optional): Filter by city
- **Headers**: Requires valid JWT token
- **Response**:
  ```json
  {
    "donations": [
      {
        "id": "number",
        "title": "string",
        "description": "string",
        "category": "string",
        "quantity": "string",
        "expiration_date": "date",
        "pickup_address": "string",
        "pickup_city": "string",
        "pickup_state": "string",
        "pickup_zip": "string",
        "status": "string",
        "donor_name": "string",
        "donor_phone": "string"
      }
    ]
  }
  ```

#### Get Donation
- **URL**: `/donations/{id}`
- **Method**: `GET`
- **Headers**: Requires valid JWT token
- **Response**:
  ```json
  {
    "donation": {
      "id": "number",
      "title": "string",
      "description": "string",
      "category": "string",
      "quantity": "string",
      "expiration_date": "date",
      "pickup_address": "string",
      "pickup_city": "string",
      "pickup_state": "string",
      "pickup_zip": "string",
      "status": "string",
      "donor_name": "string",
      "donor_phone": "string",
      "donor_email": "string"
    }
  }
  ```

#### Create Donation
- **URL**: `/donations`
- **Method**: `POST`
- **Headers**: Requires valid JWT token (donor only)
- **Body**:
  ```json
  {
    "title": "string",
    "description": "string",
    "category": "string",
    "quantity": "string",
    "expiration_date": "date",
    "pickup_address": "string",
    "pickup_city": "string",
    "pickup_state": "string",
    "pickup_zip": "string"
  }
  ```
- **Response**:
  ```json
  {
    "message": "Donation created successfully",
    "donation_id": "number"
  }
  ```

#### Update Donation Status
- **URL**: `/donations/{id}/status`
- **Method**: `PUT`
- **Headers**: Requires valid JWT token (donor or admin only)
- **Body**:
  ```json
  {
    "status": "string"  // "available", "pending", "completed", "cancelled"
  }
  ```
- **Response**:
  ```json
  {
    "message": "Donation status updated successfully"
  }
  ```

### Pickup Requests

#### List Pickup Requests
- **URL**: `/pickup`
- **Method**: `GET`
- **Query Parameters**:
  - `status` (optional): Filter by status
- **Headers**: Requires valid JWT token
- **Response**:
  ```json
  {
    "requests": [
      {
        "id": "number",
        "donation_id": "number",
        "ngo_id": "number",
        "status": "string",
        "requested_at": "datetime",
        "donation_title": "string",
        "donation_description": "string",
        "category": "string",
        "quantity": "string",
        "pickup_address": "string",
        "pickup_city": "string",
        "pickup_state": "string",
        "pickup_zip": "string",
        "donor_name": "string",
        "donor_phone": "string",
        "ngo_name": "string",
        "ngo_phone": "string"
      }
    ]
  }
  ```

#### Request Pickup
- **URL**: `/pickup`
- **Method**: `POST`
- **Headers**: Requires valid JWT token (NGO only)
- **Body**:
  ```json
  {
    "donation_id": "number"
  }
  ```
- **Response**:
  ```json
  {
    "message": "Pickup request created successfully",
    "request_id": "number"
  }
  ```

#### Update Pickup Request Status
- **URL**: `/pickup/{id}/status`
- **Method**: `PUT`
- **Headers**: Requires valid JWT token
- **Body**:
  ```json
  {
    "status": "string"  // "pending", "accepted", "rejected", "completed", "cancelled"
  }
  ```
- **Response**:
  ```json
  {
    "message": "Request status updated successfully"
  }
  ```

## Error Responses
All endpoints may return the following error response:
```json
{
  "error": true,
  "message": "string"
}
```

Common HTTP status codes:
- 200: Success
- 400: Bad Request
- 401: Unauthorized
- 403: Forbidden
- 404: Not Found
- 500: Server Error

### Driver Delivery Management

#### List Driver Tasks
- **URL**: `/driver/tasks?status=pending` (also `assigned`, `picked_up`, `in_transit`, `completed`)
- **Method**: `GET`
- **Headers**: Requires JWT authentication (driver only)

#### Accept Task
- **URL**: `/driver/tasks/accept`
- **Method**: `POST`
- **Body**: `{ "task_id": 1 }`

#### Update Task Status
- **URL**: `/driver/tasks/{id}`
- **Method**: `PUT`
- **Body**: `{ "status": "in_transit", "latitude": 31.52, "longitude": 74.35, "notes": "On route" }`
- Supported transitions include `assigned -> picked_up -> in_transit -> completed`, with cancellation support.
