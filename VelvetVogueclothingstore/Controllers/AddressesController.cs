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
public class AddressesController(ApplicationDbContext db) : ControllerBase
{
    [HttpGet]
    public async Task<IActionResult> GetMyAddresses()
    {
        var userId = GetUserId();

        var addresses = await db.UserAddresses
            .Where(x => x.UserAccountId == userId)
            .OrderByDescending(x => x.IsDefaultShipping)
            .ThenByDescending(x => x.Id)
            .ToListAsync();

        return Ok(addresses);
    }

    [HttpPost]
    public async Task<IActionResult> SaveAddress(SaveAddressRequest request)
    {
        var userId = GetUserId();

        if (request.IsDefaultShipping)
        {
            var existingDefaults = await db.UserAddresses.Where(x => x.UserAccountId == userId && x.IsDefaultShipping).ToListAsync();
            foreach (var defaultAddress in existingDefaults)
            {
                defaultAddress.IsDefaultShipping = false;
            }
        }

        var address = new UserAddress
        {
            UserAccountId = userId,
            RecipientName = request.RecipientName,
            Line1 = request.Line1,
            Line2 = request.Line2,
            City = request.City,
            State = request.State,
            PostalCode = request.PostalCode,
            Country = request.Country,
            PhoneNumber = request.PhoneNumber,
            IsDefaultShipping = request.IsDefaultShipping
        };

        db.UserAddresses.Add(address);
        await db.SaveChangesAsync();

        return Ok(address);
    }

    [HttpPut("{id:int}")]
    public async Task<IActionResult> UpdateAddress(int id, SaveAddressRequest request)
    {
        var userId = GetUserId();

        var address = await db.UserAddresses.SingleOrDefaultAsync(x => x.Id == id && x.UserAccountId == userId);
        if (address is null)
        {
            return NotFound(new { message = "Address not found." });
        }

        if (request.IsDefaultShipping)
        {
            var existingDefaults = await db.UserAddresses
                .Where(x => x.UserAccountId == userId && x.IsDefaultShipping && x.Id != id)
                .ToListAsync();

            foreach (var defaultAddress in existingDefaults)
            {
                defaultAddress.IsDefaultShipping = false;
            }
        }

        address.RecipientName = request.RecipientName;
        address.Line1 = request.Line1;
        address.Line2 = request.Line2;
        address.City = request.City;
        address.State = request.State;
        address.PostalCode = request.PostalCode;
        address.Country = request.Country;
        address.PhoneNumber = request.PhoneNumber;
        address.IsDefaultShipping = request.IsDefaultShipping;

        await db.SaveChangesAsync();
        return Ok(address);
    }

    private int GetUserId()
    {
        var userIdValue = User.FindFirstValue(ClaimTypes.NameIdentifier);
        return int.Parse(userIdValue!);
    }

    public record SaveAddressRequest(
        string RecipientName,
        string Line1,
        string? Line2,
        string City,
        string State,
        string PostalCode,
        string Country,
        string PhoneNumber,
        bool IsDefaultShipping);
}
