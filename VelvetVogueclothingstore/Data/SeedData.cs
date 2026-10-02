using VelvetVogueclothingstore.Models;
using Microsoft.AspNetCore.Identity;

namespace VelvetVogueclothingstore.Data;

public static class SeedData
{
    private const string DefaultAdminEmail = "admin@velvetvogue.com";
    private const string DefaultAdminPassword = "Admin@123";

    public static void Initialize(ApplicationDbContext db)
    {
        SeedAdmin(db);
        SeedCategories(db);

        if (db.Products.Any())
        {
            return;
        }

        var categoryIds = db.Categories.ToDictionary(x => x.Name, x => x.Id);

        db.Products.AddRange([
            new Product
            {
                Name = "Classic White Shirt",
                Description = "Breathable cotton shirt for formal and casual wear.",
                CategoryId = categoryIds["Shirts"],
                Gender = "Unisex",
                Color = "White",
                Price = 29.99m,
                ImageUrl = "/img/categories/shirts/classic-white-shirt.jpg",
                SizeStocks =
                [
                    new ProductSizeStock { Size = "Small", Quantity = 5 },
                    new ProductSizeStock { Size = "Large", Quantity = 10 },
                    new ProductSizeStock { Size = "XL", Quantity = 15 }
                ],
                DateAdded = DateTime.UtcNow.AddDays(-14)
            },
            new Product
            {
                Name = "Slim Fit Chinos",
                Description = "Smart-casual chinos with a modern slim fit.",
                CategoryId = categoryIds["Trousers"],
                Gender = "Men",
                Color = "Navy",
                Price = 39.99m,
                ImageUrl = "/img/categories/trousers/slim-fit-chinos.jpg",
                SizeStocks =
                [
                    new ProductSizeStock { Size = "Medium", Quantity = 20 },
                    new ProductSizeStock { Size = "Large", Quantity = 12 }
                ],
                DateAdded = DateTime.UtcNow.AddDays(-10)
            },
            new Product
            {
                Name = "Floral Summer Dress",
                Description = "Lightweight dress ideal for weekend outings.",
                CategoryId = categoryIds["Dresses"],
                Gender = "Women",
                Color = "Floral",
                Price = 49.99m,
                ImageUrl = "/img/categories/dresses/floral-summer-dress.jpg",
                SizeStocks =
                [
                    new ProductSizeStock { Size = "Small", Quantity = 8 },
                    new ProductSizeStock { Size = "Medium", Quantity = 14 },
                    new ProductSizeStock { Size = "Large", Quantity = 13 }
                ],
                DateAdded = DateTime.UtcNow.AddDays(-3)
            },
            new Product
            {
                Name = "Urban Denim Jacket",
                Description = "Layer-friendly denim jacket with timeless style.",
                CategoryId = categoryIds["Jackets"],
                Gender = "Unisex",
                Color = "Blue",
                Price = 59.99m,
                ImageUrl = "/img/categories/jackets/urban-denim-jacket.jpg",
                SizeStocks =
                [
                    new ProductSizeStock { Size = "Large", Quantity = 10 },
                    new ProductSizeStock { Size = "XL", Quantity = 15 }
                ],
                DateAdded = DateTime.UtcNow.AddDays(-1)
            }
        ]);

        db.SaveChanges();
    }

    private static void SeedCategories(ApplicationDbContext db)
    {
        if (db.Categories.Any())
        {
            return;
        }

        var men = new Category { Name = "Men" };
        var women = new Category { Name = "Women" };
        var kids = new Category { Name = "Kids" };

        db.Categories.AddRange([men, women, kids]);
        db.SaveChanges();

        db.Categories.AddRange([
            new Category { Name = "Shirts", ParentCategoryId = men.Id },
            new Category { Name = "Trousers", ParentCategoryId = men.Id },
            new Category { Name = "Jackets", ParentCategoryId = men.Id },
            new Category { Name = "Dresses", ParentCategoryId = women.Id },
            new Category { Name = "Tops", ParentCategoryId = women.Id },
            new Category { Name = "Skirts", ParentCategoryId = women.Id },
            new Category { Name = "Boys", ParentCategoryId = kids.Id },
            new Category { Name = "Girls", ParentCategoryId = kids.Id },
            new Category { Name = "Baby", ParentCategoryId = kids.Id }
        ]);

        db.SaveChanges();
    }

    private static void SeedAdmin(ApplicationDbContext db)
    {
        var admin = db.UserAccounts.SingleOrDefault(x => x.Email == DefaultAdminEmail);

        if (admin is null)
        {
            admin = new UserAccount
            {
                FullName = "Velvet Vogue Admin",
                Email = DefaultAdminEmail,
                Role = "Admin"
            };

            db.UserAccounts.Add(admin);
        }

        var hasher = new PasswordHasher<UserAccount>();
        admin.Role = "Admin";
        admin.PasswordHash = hasher.HashPassword(admin, DefaultAdminPassword);
        db.SaveChanges();
    }
}
