# Velvet Vogue Clothing Store - Run Guide

## 1) Prerequisites

- .NET 10 SDK
- SQL Server LocalDB (or SQL Server instance)
- Visual Studio 2026 (recommended) or `dotnet` CLI

Check SDK:

```powershell
dotnet --version
```

---

## 2) Database Configuration

Current connection string (`appsettings.json`):

```json
"ConnectionStrings": {
  "DefaultConnection": "Server=(localdb)\\mssqllocaldb;Database=VelvetVogueDb;Trusted_Connection=True;MultipleActiveResultSets=true;TrustServerCertificate=True"
}
```

If you use another SQL Server, update `DefaultConnection`.

### Important behavior in Development
In `Program.cs`, when environment is Development:

- `db.Database.EnsureDeleted();`
- `db.Database.EnsureCreated();`
- `SeedData.Initialize(db);`

This means every app start in Development recreates the database and reseeds data.

---

## 3) Run the Project

From solution/project folder:

```powershell
dotnet restore
dotnet build
dotnet run --project .\VelvetVogueclothingstore\VelvetVogueclothingstore.csproj
```

Then open the URL shown in terminal (for example `https://localhost:xxxx`).

---

## 4) Seeded Data / Default Accounts

`SeedData` creates:

- Main categories: Men, Women, Kids
- Subcategories and sample products
- Default admin account:
  - **Email:** `admin@velvetvogue.com`
  - **Password:** `Admin@123`

---

## 5) Main Pages (User)

- Home: `/`
- Products: `/Home/Products`
- Cart + Checkout: `/Home/Cart`
- Login/Register: `/Home/Login`
- User Details (after login): `/Home/Account`
- Orders: `/Home/Orders`
- Contact: `/Home/Contact`

### User flow
1. Open `/Home/Login`
2. Login or register
3. Go to Products and add to cart
4. Open Cart, choose shipping address + payment method (Cash on Delivery)
5. Place order
6. View orders in `/Home/Orders`

---

## 6) Admin Pages

- Admin Login: `/admin/login`
- Admin Dashboard: `/admin`

### Admin flow
1. Go to `/admin/login`
2. Sign in with default admin account
3. Manage products, categories, orders, and cart overview from admin dashboard

Note: If logged in as Admin, storefront pages redirect to admin dashboard.

---

## 7) Authentication and Roles

- Cookie authentication is enabled.
- Customer login endpoint: `/api/auth/login`
- Admin login endpoint: `/api/auth/admin-login`
- Logout endpoint: `/api/auth/logout`

Role behavior:
- **Customer**: storefront pages
- **Admin**: admin-only dashboard/navigation

---

## 8) Troubleshooting

### Build works but app shows old UI
- Stop debugging session and rerun, or use Hot Reload.

### SQL connection errors
- Verify LocalDB is installed.
- Verify connection string in `appsettings.json`.

### Login fails for admin
- Ensure you are using:
  - `admin@velvetvogue.com`
  - `Admin@123`
- Since DB is reseeded in Development, these credentials reset each run.

---

## 9) Optional Production Note

For production, remove `EnsureDeleted()` logic and use EF migrations instead of recreating DB at startup.
