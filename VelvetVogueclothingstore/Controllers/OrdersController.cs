using System.Security.Claims;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using Microsoft.EntityFrameworkCore;
using VelvetVogueclothingstore.Data;
using VelvetVogueclothingstore.Models;

namespace VelvetVogueclothingstore.Controllers;

[ApiController]
[Route("api/[controller]")]
[Authorize]
public class OrdersController(ApplicationDbContext db) : ControllerBase
{
    [HttpGet("mine")]
    public async Task<IActionResult> MyOrders()
    {
        var userId = GetUserId();

        var orders = await db.Orders
            .Where(x => x.UserAccountId == userId)
            .OrderByDescending(x => x.CreatedAtUtc)
            .Select(x => new
            {
                x.Id,
                x.Status,
                x.PaymentMethod,
                x.CreatedAtUtc,
                x.TotalAmount,
                ShippingAddress = new
                {
                    x.UserAddress!.RecipientName,
                    x.UserAddress.Line1,
                    x.UserAddress.Line2,
                    x.UserAddress.City,
                    x.UserAddress.State,
                    x.UserAddress.PostalCode,
                    x.UserAddress.Country
                },
                Items = x.Items.Select(i => new
                {
                    i.ProductId,
                    i.ProductNameSnapshot,
                    i.SelectedSizeSnapshot,
                    i.UnitPrice,
                    i.Quantity,
                    i.LineTotal
                })
            })
            .ToListAsync();

        return Ok(orders);
    }

    [HttpPost("checkout")]
    public async Task<IActionResult> Checkout(CheckoutRequest request)
    {
        var userId = GetUserId();

        var paymentMethod = string.IsNullOrWhiteSpace(request.PaymentMethod)
            ? "Cash on Delivery"
            : request.PaymentMethod.Trim();

        if (!string.Equals(paymentMethod, "Cash on Delivery", StringComparison.OrdinalIgnoreCase))
        {
            return BadRequest(new { message = "Only Cash on Delivery is available now." });
        }

        var address = await db.UserAddresses.SingleOrDefaultAsync(x => x.Id == request.UserAddressId && x.UserAccountId == userId);
        if (address is null)
        {
            return BadRequest(new { message = "Shipping address not found." });
        }

        var cartItems = await db.CartItems
            .Where(x => x.UserAccountId == userId)
            .Include(x => x.Product)
            .ThenInclude(x => x!.SizeStocks)
            .ToListAsync();

        if (cartItems.Count == 0)
        {
            return BadRequest(new { message = "Cart is empty." });
        }

        foreach (var item in cartItems)
        {
            var sizeStock = item.Product?.SizeStocks.SingleOrDefault(s => s.Size == item.SelectedSize);
            if (item.Product is null || sizeStock is null || sizeStock.Quantity < item.Quantity)
            {
                return BadRequest(new { message = $"Insufficient stock for product ID {item.ProductId} size {item.SelectedSize}." });
            }
        }

        var orderItems = cartItems.Select(item => new OrderItem
        {
            ProductId = item.ProductId,
            ProductNameSnapshot = item.Product!.Name,
            SelectedSizeSnapshot = item.SelectedSize,
            UnitPrice = item.Product.Price,
            Quantity = item.Quantity,
            LineTotal = item.Quantity * item.Product.Price
        }).ToList();

        var order = new Order
        {
            UserAccountId = userId,
            UserAddressId = address.Id,
            Status = "Pending",
            PaymentMethod = "Cash on Delivery",
            TotalAmount = orderItems.Sum(x => x.LineTotal),
            Items = orderItems
        };

        foreach (var item in cartItems)
        {
            var sizeStock = item.Product!.SizeStocks.Single(x => x.Size == item.SelectedSize);
            sizeStock.Quantity -= item.Quantity;
        }

        db.Orders.Add(order);
        db.CartItems.RemoveRange(cartItems);

        await db.SaveChangesAsync();

        return Ok(new
        {
            order.Id,
            order.Status,
            order.PaymentMethod,
            order.TotalAmount,
            order.CreatedAtUtc
        });
    }

    private int GetUserId()
    {
        var userIdValue = User.FindFirstValue(ClaimTypes.NameIdentifier);
        return int.Parse(userIdValue!);
    }

    public record CheckoutRequest(int UserAddressId, string? PaymentMethod);
}
