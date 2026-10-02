namespace VelvetVogueclothingstore.Models;

public class CartItem
{
    public int Id { get; set; }
    public int UserAccountId { get; set; }
    public UserAccount? UserAccount { get; set; }
    public int ProductId { get; set; }
    public Product? Product { get; set; }
    public string SelectedSize { get; set; } = string.Empty;
    public int Quantity { get; set; }
}
