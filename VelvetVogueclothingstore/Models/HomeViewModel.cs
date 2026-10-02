namespace VelvetVogueclothingstore.Models;

public class HomeViewModel
{
    public List<HomeMainCategoryViewModel> Categories { get; set; } = [];
    public List<Product> FeaturedProducts { get; set; } = [];
    public List<HomeProductCardViewModel> NewArrivalProducts { get; set; } = [];
    public List<HeroSlideViewModel> HeroSlides { get; set; } = [];
}

public class HomeMainCategoryViewModel
{
    public string Name { get; set; } = string.Empty;
    public List<string> SubCategories { get; set; } = [];
}

public class HomeProductCardViewModel
{
    public string Name { get; set; } = string.Empty;
    public decimal Price { get; set; }
    public string ImageUrl { get; set; } = string.Empty;
}

public class HeroSlideViewModel
{
    public string Title { get; set; } = string.Empty;
    public string Subtitle { get; set; } = string.Empty;
    public string ImageUrl { get; set; } = string.Empty;
}
