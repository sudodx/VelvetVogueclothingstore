namespace VelvetVogueclothingstore.Models;

public class Order
{
    public int Id { get; set; }
    public int UserAccountId { get; set; }
    public UserAccount? UserAccount { get; set; }
    public int UserAddressId { get; set; }
    public UserAddress? UserAddress { get; set; }
    public DateTime CreatedAtUtc { get; set; } = DateTime.UtcNow;
    public string Status { get; set; } = "Pending";
    public string PaymentMethod { get; set; } = "Cash on Delivery";
    public decimal TotalAmount { get; set; }
    public ICollection<OrderItem> Items { get; set; } = [];
}
