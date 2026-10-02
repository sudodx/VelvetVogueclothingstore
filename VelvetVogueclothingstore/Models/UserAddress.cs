namespace VelvetVogueclothingstore.Models;

public class UserAddress
{
    public int Id { get; set; }
    public int UserAccountId { get; set; }
    public UserAccount? UserAccount { get; set; }
    public string RecipientName { get; set; } = string.Empty;
    public string Line1 { get; set; } = string.Empty;
    public string? Line2 { get; set; }
    public string City { get; set; } = string.Empty;
    public string State { get; set; } = string.Empty;
    public string PostalCode { get; set; } = string.Empty;
    public string Country { get; set; } = string.Empty;
    public string PhoneNumber { get; set; } = string.Empty;
    public bool IsDefaultShipping { get; set; }
}
